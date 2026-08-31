<?php

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Inventory\Enums\EquipmentAssignmentStatus;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\TerminationRecord;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{property: Property, contractor: Person, wo: WorkOrder, period: PayrollPeriod} */
function terminationScenario(): array
{
    $property = Property::factory()->create();
    $position = Position::factory()->create();
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $wo = WorkOrder::factory()->create([
        'person_id' => $contractor->id, 'property_id' => $property->id, 'position_id' => $position->id,
        'status' => WorkOrderStatus::Active,
    ]);

    $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id, 'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open,
    ]);

    return compact('property', 'contractor', 'wo', 'period');
}

/** Build a $40 outstanding uniform charge spread over one open period entry. */
function outstandingCharge(Person $contractor, PayrollPeriod $period, int $cents = 4000): ContractorChargeSchedule
{
    $schedule = ContractorChargeSchedule::create([
        'person_id' => $contractor->id, 'total_amount' => $cents, 'num_payments' => 1,
        'amount_per_payment' => $cents, 'status' => ChargeScheduleStatus::Active,
    ]);
    $schedule->entries()->create([
        'payroll_period_id' => $period->id, 'amount' => $cents, 'payment_index' => 1,
        'status' => ChargeEntryStatus::Scheduled,
    ]);

    return $schedule;
}

function initiateTermination(array $s, ?Person $actor = null): Workflow
{
    test()->actingAs($actor ?? person('admin'))
        ->post(main('/admin/terminations'), [
            'person_id' => $s['contractor']->id,
            'effective_date' => Carbon::now()->toDateString(),
            'termination_type' => 'voluntary',
            'reason_category' => 'resignation',
            'notes' => 'Moving on',
            'rehireable' => true,
        ])->assertRedirect();

    return Workflow::query()->where('type', WorkflowType::Termination->value)->latest('id')->firstOrFail();
}

it('initiates a termination: status pending, WO closed, pending workflow cancelled, record created', function () {
    $s = terminationScenario();

    // A pending pay-increase workflow about this contractor should be auto-cancelled.
    $other = Workflow::factory()->create([
        'type' => WorkflowType::PayIncrease->value,
        'subject_type' => (new Person)->getMorphClass(),
        'subject_id' => $s['contractor']->id,
        'status' => WorkflowStatus::InProgress,
    ]);

    $workflow = initiateTermination($s);

    expect($s['contractor']->fresh()->status)->toBe(PersonStatus::PendingTermination)
        ->and($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Closed)
        ->and($other->fresh()->status)->toBe(WorkflowStatus::Cancelled)
        ->and($workflow->currentStep()->step_key)->toBe('recover_equipment');

    $this->assertDatabaseHas('termination_records', [
        'workflow_id' => $workflow->id, 'person_id' => $s['contractor']->id,
        'termination_type' => 'voluntary', 'reason_category' => 'resignation',
    ]);
});

it('recovers equipment (returned + lost) and advances to move file', function () {
    $s = terminationScenario();
    $variant = ItemVariant::factory()->create();
    $keep = EquipmentAssignment::factory()->create([
        'assigned_to_person_id' => $s['contractor']->id, 'item_variant_id' => $variant->id,
        'status' => EquipmentAssignmentStatus::Assigned, 'quantity' => 1,
    ]);
    $lost = EquipmentAssignment::factory()->create([
        'assigned_to_person_id' => $s['contractor']->id, 'item_variant_id' => $variant->id,
        'status' => EquipmentAssignmentStatus::Assigned, 'quantity' => 1,
    ]);

    $workflow = initiateTermination($s);

    $this->actingAs(person('admin'))
        ->post(main("/admin/terminations/{$workflow->id}/recover-equipment"), [
            'items' => [
                ['assignment_id' => $keep->id, 'returned' => true],
                ['assignment_id' => $lost->id, 'returned' => false, 'notes' => 'never returned'],
            ],
        ])->assertRedirect();

    expect($keep->fresh()->status)->toBe(EquipmentAssignmentStatus::Returned)
        ->and($lost->fresh()->status)->toBe(EquipmentAssignmentStatus::Lost)
        ->and($workflow->fresh()->currentStep()->step_key)->toBe('move_file');
});

it('moving the file terminates the person', function () {
    $s = terminationScenario();
    $workflow = initiateTermination($s);

    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/recover-equipment"), ['items' => []]);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/move-file"))->assertRedirect();

    expect($s['contractor']->fresh()->status)->toBe(PersonStatus::Terminated)
        ->and($s['contractor']->fresh()->terminated_at)->not->toBeNull()
        ->and($workflow->fresh()->currentStep()->step_key)->toBe('process_final_paycheck');

    $this->assertDatabaseMissing('termination_records', ['workflow_id' => $workflow->id, 'terminated_at' => null]);
});

it('processes the final paycheck: one capped non-billable deduction, schedules accelerated, workflow completes', function () {
    $s = terminationScenario();
    $schedule = outstandingCharge($s['contractor'], $s['period'], 4000);
    TimeSummary::factory()->create([
        'person_id' => $s['contractor']->id, 'work_order_id' => $s['wo']->id,
        'property_id' => $s['property']->id, 'payroll_period_id' => $s['period']->id, 'total_pay' => 20000,
    ]);

    $workflow = initiateTermination($s);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/recover-equipment"), ['items' => []]);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/move-file"));
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/final-paycheck"))->assertRedirect();

    $deductions = TimeEntryAdjustment::query()->where('person_id', $s['contractor']->id)->get();
    expect($deductions)->toHaveCount(1)
        ->and($deductions->first()->value)->toBe(4000)
        ->and($deductions->first()->type)->toBe(AdjustmentType::Deduction)
        ->and($deductions->first()->is_billable)->toBeFalse()
        ->and($deductions->first()->source_type)->toBe(AdjustmentSourceType::SupplyRequestTerminationConsolidation)
        ->and($schedule->fresh()->status)->toBe(ChargeScheduleStatus::AcceleratedToFinalPaycheck)
        ->and($s['contractor']->outstandingChargeBalance())->toBe(0)
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Completed);

    $this->assertDatabaseHas('termination_records', [
        'workflow_id' => $workflow->id, 'final_paycheck_consolidated_cents' => 4000, 'final_paycheck_remainder_cents' => 0,
    ]);
    expect(TerminationRecord::query()->where('workflow_id', $workflow->id)->firstOrFail()->quickbooks_removed_at)->not->toBeNull();
});

it('caps the consolidated deduction at available pay and writes off the remainder', function () {
    $s = terminationScenario();
    outstandingCharge($s['contractor'], $s['period'], 4000);
    // Only $10 of pay available → deduction capped at 1000, $30 written off.
    TimeSummary::factory()->create([
        'person_id' => $s['contractor']->id, 'work_order_id' => $s['wo']->id,
        'property_id' => $s['property']->id, 'payroll_period_id' => $s['period']->id, 'total_pay' => 1000,
    ]);

    $workflow = initiateTermination($s);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/recover-equipment"), ['items' => []]);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/move-file"));
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/final-paycheck"));

    expect((int) TimeEntryAdjustment::query()->where('person_id', $s['contractor']->id)->sum('value'))->toBe(1000);
    $this->assertDatabaseHas('termination_records', [
        'workflow_id' => $workflow->id, 'final_paycheck_consolidated_cents' => 1000, 'final_paycheck_remainder_cents' => 3000,
    ]);
});

it('flags the final paycheck when there is no open payroll period', function () {
    $s = terminationScenario();
    $s['period']->update(['status' => PayrollPeriodStatus::Closed]);
    $schedule = outstandingCharge($s['contractor'], $s['period'], 4000);

    $workflow = initiateTermination($s);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/recover-equipment"), ['items' => []]);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/move-file"));
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/final-paycheck"))->assertRedirect();

    // No deduction created, schedule untouched, but the workflow still completes.
    expect(TimeEntryAdjustment::query()->where('person_id', $s['contractor']->id)->count())->toBe(0)
        ->and($schedule->fresh()->status)->toBe(ChargeScheduleStatus::Active)
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Completed);
});

it('lets a super-admin cancel before the file moves, reverting status', function () {
    $s = terminationScenario();
    $workflow = initiateTermination($s);

    $this->actingAs(person('super_admin'))
        ->post(main("/admin/terminations/{$workflow->id}/cancel"), ['reason' => 'rescinded'])
        ->assertRedirect();

    expect($s['contractor']->fresh()->status)->toBe(PersonStatus::ContractorActive)
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Cancelled);

    $this->assertDatabaseHas('termination_records', ['workflow_id' => $workflow->id, 'cancellation_reason' => 'rescinded']);
});

it('forbids cancelling once the file has moved', function () {
    $s = terminationScenario();
    $workflow = initiateTermination($s);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/recover-equipment"), ['items' => []]);
    $this->actingAs(person('admin'))->post(main("/admin/terminations/{$workflow->id}/move-file"));

    $this->actingAs(person('super_admin'))
        ->post(main("/admin/terminations/{$workflow->id}/cancel"), ['reason' => 'too late'])
        ->assertSessionHasErrors('workflow');
});

it('gates actions by permission', function () {
    $s = terminationScenario();

    // A contractor cannot initiate.
    $this->actingAs(person('contractor'))
        ->post(main('/admin/terminations'), [
            'person_id' => $s['contractor']->id, 'effective_date' => Carbon::now()->toDateString(),
            'termination_type' => 'voluntary', 'reason_category' => 'resignation',
        ])->assertForbidden();

    $workflow = initiateTermination($s);

    // Payroll lacks physical-tasks permission for equipment recovery.
    $this->actingAs(person('payroll'))
        ->post(main("/admin/terminations/{$workflow->id}/recover-equipment"), ['items' => []])
        ->assertForbidden();

    // Front desk lacks payroll-tasks permission (and a non-super-admin cannot cancel).
    $this->actingAs(person('front_desk'))
        ->post(main("/admin/terminations/{$workflow->id}/cancel"), ['reason' => 'x'])
        ->assertForbidden();
});
