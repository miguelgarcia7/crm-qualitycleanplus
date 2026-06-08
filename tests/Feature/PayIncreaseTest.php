<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\WorkflowNotice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{wo: WorkOrder, property: Property, contractor: Person, pm: Person, period: PayrollPeriod} */
function payIncreaseScenario(): array
{
    $property = Property::factory()->create();
    $position = Position::factory()->create();
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $wo = WorkOrder::factory()->create([
        'person_id' => $contractor->id, 'property_id' => $property->id, 'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'status' => WorkOrderStatus::Active,
    ]);

    $pm = person('property_manager');
    $property->assignments()->create(['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

    $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id, 'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open,
    ]);

    return compact('wo', 'property', 'contractor', 'pm', 'period');
}

it('lets a PM submit and a recruiter approve a pay increase', function () {
    Notification::fake();
    $s = payIncreaseScenario();

    $this->actingAs($s['pm'])
        ->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'great work'])
        ->assertRedirect();

    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();
    expect($workflow->status)->toBe(WorkflowStatus::InProgress)
        ->and($workflow->currentStep()->step_key)->toBe('approve_pay_increase');

    $this->actingAs(person('admin'))
        ->post(main("/admin/pay-increases/{$workflow->id}/approve"), [
            'pay_rate' => 21, 'bill_rate' => 31, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 46.5,
            'effective_period_id' => $s['period']->id,
        ])->assertRedirect();

    expect($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Closed)
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Completed);

    $new = WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->firstOrFail();
    expect($new->source)->toBe(WorkOrderSource::PayIncrease)
        ->and($new->bill_rate)->toBe(3100)
        ->and($new->pay_rate)->toBe(2100);

    Notification::assertSentTo($s['contractor'], WorkflowNotice::class);
    Notification::assertSentTo($s['pm'], WorkflowNotice::class);
});

it('enforces the PM bill-rate floor on approval', function () {
    $s = payIncreaseScenario();
    $this->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'x']);
    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();

    // current bill $30 + $1 PM increase = $31 floor; approving at $30.50 must fail.
    $this->actingAs(person('admin'))
        ->post(main("/admin/pay-increases/{$workflow->id}/approve"), [
            'pay_rate' => 21, 'bill_rate' => 30.5, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 45.75,
            'effective_period_id' => $s['period']->id,
        ])->assertSessionHasErrors('bill_rate');

    expect($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Active)
        ->and(WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->exists())->toBeFalse();
});

it('declining a pay increase rejects the workflow and creates no WO', function () {
    Notification::fake();
    $s = payIncreaseScenario();
    $this->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'x']);
    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();

    $this->actingAs(person('admin'))
        ->post(main("/admin/pay-increases/{$workflow->id}/decline"), ['reason' => 'budget'])
        ->assertRedirect();

    expect($workflow->fresh()->status)->toBe(WorkflowStatus::Rejected)
        ->and(WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->exists())->toBeFalse();
});

it('applies a recruiter-initiated pay increase immediately', function () {
    $s = payIncreaseScenario();

    $this->actingAs(person('admin'))
        ->post(main('/admin/pay-increases'), [
            'work_order_id' => $s['wo']->id,
            'pay_rate' => 22, 'bill_rate' => 33, 'ot_pay_rate' => 33, 'ot_bill_rate' => 49.5,
            'effective_period_id' => $s['period']->id, 'reason' => 'merit',
        ])->assertRedirect();

    $new = WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->firstOrFail();
    expect($new->source)->toBe(WorkOrderSource::PayIncrease)
        ->and($new->pay_rate)->toBe(2200)
        ->and($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Closed);
});

it('forbids a role without approve permission', function () {
    $s = payIncreaseScenario();
    $this->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'x']);
    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();

    $this->actingAs(person('front_desk'))
        ->post(main("/admin/pay-increases/{$workflow->id}/approve"), [
            'pay_rate' => 21, 'bill_rate' => 31, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 46.5,
            'effective_period_id' => $s['period']->id,
        ])->assertForbidden();
});
