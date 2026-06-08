<?php

use App\Domain\Inventory\Models\ContractorChargeSchedule;
use App\Domain\Inventory\Models\ContractorChargeScheduleEntry;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\WorkflowNotice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('closes the old WO and opens a new one at the new property', function () {
    Notification::fake();
    $recruiter2 = person('recruiter');
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $oldWo = WorkOrder::factory()->create(['person_id' => $contractor->id, 'status' => WorkOrderStatus::Active]);
    $newProperty = Property::factory()->create();
    $position = Position::factory()->create();

    $this->actingAs(person('admin'))
        ->post(main("/admin/work-orders/{$oldWo->id}/transfer"), [
            'effective_date' => Carbon::now()->toDateString(),
            'new_property_id' => $newProperty->id,
            'new_position_id' => $position->id,
            'new_recruiter_id' => $recruiter2->id,
            'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
            'reason' => 'operational',
        ])->assertRedirect();

    expect($oldWo->fresh()->status)->toBe(WorkOrderStatus::Closed);

    $new = WorkOrder::query()->where('parent_wo_id', $oldWo->id)->firstOrFail();
    expect($new->status)->toBe(WorkOrderStatus::Active)
        ->and($new->source)->toBe(WorkOrderSource::Transfer)
        ->and($new->property_id)->toBe($newProperty->id)
        ->and($new->pay_rate)->toBe(2000)
        ->and($contractor->fresh()->primary_recruiter_id)->toBe($recruiter2->id);

    Notification::assertSentTo($recruiter2, WorkflowNotice::class);
});

it('remaps scheduled charge entries to the new property periods', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $oldWo = WorkOrder::factory()->create(['person_id' => $contractor->id, 'status' => WorkOrderStatus::Active]);
    $newProperty = Property::factory()->create();
    $position = Position::factory()->create();
    $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

    $oldPeriod = PayrollPeriod::factory()->create(['property_id' => $oldWo->property_id, 'week_start' => $monday->toDateString(), 'week_end' => $monday->copy()->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open]);
    $newPeriod = PayrollPeriod::factory()->create(['property_id' => $newProperty->id, 'week_start' => $monday->toDateString(), 'week_end' => $monday->copy()->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open]);

    $schedule = ContractorChargeSchedule::factory()->create(['person_id' => $contractor->id]);
    $entry = ContractorChargeScheduleEntry::create(['schedule_id' => $schedule->id, 'payroll_period_id' => $oldPeriod->id, 'amount' => 2000, 'payment_index' => 1, 'status' => 'scheduled']);

    $this->actingAs(person('admin'))
        ->post(main("/admin/work-orders/{$oldWo->id}/transfer"), [
            'effective_date' => Carbon::now()->toDateString(),
            'new_property_id' => $newProperty->id,
            'new_position_id' => $position->id,
            'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
            'reason' => 'operational',
        ])->assertRedirect();

    expect($entry->fresh()->payroll_period_id)->toBe($newPeriod->id);
});

it('forbids a recruiter not assigned to the property', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $oldWo = WorkOrder::factory()->create(['person_id' => $contractor->id, 'status' => WorkOrderStatus::Active]);

    $this->actingAs(person('recruiter'))
        ->post(main("/admin/work-orders/{$oldWo->id}/transfer"), [
            'effective_date' => Carbon::now()->toDateString(),
            'new_property_id' => Property::factory()->create()->id,
            'new_position_id' => Position::factory()->create()->id,
            'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
            'reason' => 'operational',
        ])->assertForbidden();
});
