<?php

use App\Domain\Adjustments\Actions\CreateManualAdjustment;
use App\Domain\Adjustments\Actions\DeleteAdjustment;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Actions\DeleteTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** A work order at a property with an open payroll period for this week. */
function auditScenario(): array
{
    $property = Property::factory()->create(['timezone' => 'America/Phoenix']);
    $workOrder = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'position_id' => Position::factory()->create()->id,
    ]);

    $monday = Carbon::now('America/Phoenix')->startOfWeek(Carbon::MONDAY);
    PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(),
        'status' => PayrollPeriodStatus::Open,
    ]);

    return ['workOrder' => $workOrder, 'monday' => $monday, 'property' => $property];
}

it('records who added a punch, against the contractor', function () {
    ['workOrder' => $wo, 'monday' => $monday] = auditScenario();
    $recruiter = person('recruiter');

    $entry = app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(),
        'start_time' => '09:00',
        'end_time' => '17:30',
        'entry_type' => 'work',
    ], $recruiter);

    $log = Activity::query()->where('log_name', 'payroll')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('created')
        ->and($log->causer_id)->toBe($recruiter->id)
        // Against the contractor, so it shows on their profile History tab.
        ->and($log->subject_id)->toBe($wo->person_id)
        ->and($log->description)->toContain('9:00 am')
        ->and($log->properties['time_entry_id'])->toBe($entry->id)
        ->and($log->properties['minutes'])->toBe(510);
});

it('records a removed punch, with details captured before the row goes', function () {
    ['workOrder' => $wo, 'monday' => $monday] = auditScenario();
    $recruiter = person('recruiter');

    $entry = app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(),
        'start_time' => '09:00',
        'end_time' => '17:30',
        'entry_type' => 'work',
    ], $recruiter);

    $this->actingAs($recruiter);
    app(DeleteTimeEntry::class)->handle($entry->fresh());

    $log = Activity::query()->where('log_name', 'payroll')->latest('id')->first();

    expect($log->event)->toBe('deleted')
        ->and($log->causer_id)->toBe($recruiter->id)
        ->and($log->description)->toContain('9:00 am')
        // The row is gone by the time this is written, so the times have to
        // have been captured beforehand.
        ->and($log->properties['minutes'])->toBe(510)
        ->and($log->properties['time_entry_id'])->toBe($entry->id);
});

it('leaves a create-then-delete pair that reads as the correction it is', function () {
    ['workOrder' => $wo, 'monday' => $monday] = auditScenario();
    $recruiter = person('recruiter');
    $this->actingAs($recruiter);

    // There is no edit endpoint — a correction is a delete plus a create.
    $wrong = app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:30', 'entry_type' => 'work',
    ], $recruiter);
    app(DeleteTimeEntry::class)->handle($wrong->fresh());
    app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '13:00', 'entry_type' => 'work',
    ], $recruiter);

    $events = Activity::query()->where('log_name', 'payroll')->orderBy('id')->pluck('event')->all();

    expect($events)->toBe(['created', 'deleted', 'created']);
});

it('records adjustments added and removed', function () {
    ['workOrder' => $wo, 'property' => $property] = auditScenario();
    $period = PayrollPeriod::query()->where('property_id', $property->id)->firstOrFail();
    $manager = person('office_manager');
    $this->actingAs($manager);

    $adjustment = app(CreateManualAdjustment::class)->handle($period, [
        'person_id' => $wo->person_id,
        'work_order_id' => $wo->id,
        'value' => 7500,
        'type' => 'incentive',
        'is_billable' => true,
    ], $manager);

    $added = Activity::query()->where('log_name', 'payroll')->latest('id')->first();
    expect($added->description)->toBe('Added a billable incentive of $75.00')
        ->and($added->properties['is_billable'])->toBeTrue();

    app(DeleteAdjustment::class)->handle($adjustment->fresh());

    $removed = Activity::query()->where('log_name', 'payroll')->latest('id')->first();
    expect($removed->event)->toBe('deleted')
        ->and($removed->description)->toBe('Removed the $75.00 incentive')
        ->and($removed->properties['value'])->toBe(7500);
});
