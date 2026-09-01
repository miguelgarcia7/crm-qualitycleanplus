<?php

use App\Domain\Adjustments\Actions\CreateManualAdjustment;
use App\Domain\Adjustments\Actions\DeleteAdjustment;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Actions\DeleteTimeEntry;
use App\Domain\Time\Actions\UpdateTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

it('records a corrected punch as one event carrying the old and new times', function () {
    ['workOrder' => $wo, 'monday' => $monday] = auditScenario();
    $recruiter = person('recruiter');
    $this->actingAs($recruiter);

    $entry = app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:30', 'entry_type' => 'work',
    ], $recruiter);

    app(UpdateTimeEntry::class)->handle($entry->fresh(), [
        'start_time' => '09:00', 'end_time' => '13:00', 'entry_type' => 'work',
    ]);

    $log = Activity::query()->where('log_name', 'payroll')->latest('id')->first();

    expect($log->event)->toBe('updated')
        ->and($log->causer_id)->toBe($recruiter->id)
        ->and($log->subject_id)->toBe($wo->person_id)
        ->and($log->description)->toContain('from 9:00 am–5:30 pm to 9:00 am–1:00 pm')
        ->and($log->properties['from']['minutes'])->toBe(510)
        ->and($log->properties['to']['minutes'])->toBe(240);

    // One event, not the delete/create pair a correction used to leave behind.
    $events = Activity::query()->where('log_name', 'payroll')->orderBy('id')->pluck('event')->all();
    expect($events)->toBe(['created', 'updated'])
        ->and($entry->fresh()->duration_minutes)->toBe(240);
});

it('keeps the original rate snapshots when a punch is corrected', function () {
    ['workOrder' => $wo, 'monday' => $monday] = auditScenario();
    $recruiter = person('recruiter');
    $this->actingAs($recruiter);

    $entry = app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:30', 'entry_type' => 'work',
    ], $recruiter);

    // The rates move after the work was done; the correction must not re-price it.
    $wo->update(['pay_rate' => $wo->pay_rate + 500, 'bill_rate' => $wo->bill_rate + 500]);

    $updated = app(UpdateTimeEntry::class)->handle($entry->fresh(), [
        'start_time' => '09:00', 'end_time' => '13:00', 'entry_type' => 'work',
    ]);

    expect($updated->pay_rate_snapshot)->toBe($entry->pay_rate_snapshot)
        ->and($updated->bill_rate_snapshot)->toBe($entry->bill_rate_snapshot);
});

it('marks a punch as touched by someone other than the contractor', function () {
    ['workOrder' => $wo, 'monday' => $monday] = auditScenario();
    $recruiter = person('recruiter');
    $this->actingAs($recruiter);

    $entry = app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:30', 'entry_type' => 'work',
    ], $recruiter);

    expect($entry->was_updated)->toBeFalse();

    app(UpdateTimeEntry::class)->handle($entry->fresh(), [
        'start_time' => '09:00', 'end_time' => '13:00', 'entry_type' => 'work',
    ]);

    // The punch is no longer purely the contractor's own record.
    expect($entry->fresh()->was_updated)->toBeTrue();
});

it('refuses to correct a punch once the week is locked', function () {
    ['workOrder' => $wo, 'monday' => $monday, 'property' => $property] = auditScenario();
    $recruiter = person('recruiter');
    $this->actingAs($recruiter);

    $entry = app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $monday->toDateString(), 'start_time' => '09:00', 'end_time' => '17:30', 'entry_type' => 'work',
    ], $recruiter);

    PayrollPeriod::query()->where('property_id', $property->id)->update(['status' => PayrollPeriodStatus::Locked]);

    expect(fn () => app(UpdateTimeEntry::class)->handle($entry->fresh(), [
        'start_time' => '09:00', 'end_time' => '13:00', 'entry_type' => 'work',
    ]))->toThrow(ValidationException::class);

    expect($entry->fresh()->duration_minutes)->toBe(510);
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
