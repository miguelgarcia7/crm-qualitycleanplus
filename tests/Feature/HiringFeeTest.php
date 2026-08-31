<?php

use App\Domain\Adjustments\Actions\AllocateChargeScheduleEntries;
use App\Domain\Adjustments\Actions\ScheduleHiringFee;
use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Adjustments\Enums\ChargeReason;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Jobs\ApplyScheduledContractorCharges;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** A contractor placed at a property, with `$weeks` open payroll periods. */
function placedContractor(int $weeks = 20): Person
{
    $property = Property::factory()->create();
    // A hiring fee belongs to a contractor — the factory defaults to staff.
    $person = Person::factory()->contractorActive()->create();
    WorkOrder::factory()->create(['person_id' => $person->id, 'property_id' => $property->id]);

    $week = now()->startOfWeek();
    for ($i = 0; $i < $weeks; $i++) {
        PayrollPeriod::factory()->create([
            'property_id' => $property->id,
            'week_start' => $week->copy()->addWeeks($i)->toDateString(),
            'week_end' => $week->copy()->addWeeks($i)->addDays(6)->toDateString(),
            'status' => PayrollPeriodStatus::Open,
        ]);
    }

    return $person;
}

it('creates a $250 fee taken $20 at a time', function () {
    $schedule = app(ScheduleHiringFee::class)->handle(placedContractor());

    expect($schedule->reason)->toBe(ChargeReason::HiringFee)
        ->and($schedule->total_amount)->toBe(250_00)
        ->and($schedule->amount_per_payment)->toBe(20_00)
        ->and($schedule->num_payments)->toBe(13); // 12 × $20 + $10
});

it('never collects more than the fee, so the last payment is the remainder', function () {
    $person = placedContractor();
    $schedule = app(ScheduleHiringFee::class)->handle($person);

    app(AllocateChargeScheduleEntries::class)->handle($schedule);

    $amounts = $schedule->fresh()->entries->pluck('amount');

    expect($amounts->sum())->toBe(250_00)
        ->and($amounts->last())->toBe(10_00)
        ->and($amounts->count())->toBe(13);
});

it('allocates only as far as periods exist, and continues later', function () {
    // Four open periods — far short of the 13 the fee needs.
    $person = placedContractor(weeks: 4);
    $schedule = app(ScheduleHiringFee::class)->handle($person);
    $allocate = app(AllocateChargeScheduleEntries::class);

    $allocate->handle($schedule);
    expect($schedule->fresh()->entries)->toHaveCount(4);

    // As EnsurePayrollPeriods opens more, the rest is scheduled.
    $property = $person->workOrders()->first()->property_id;
    $week = now()->startOfWeek()->addWeeks(4);
    for ($i = 0; $i < 12; $i++) {
        PayrollPeriod::factory()->create([
            'property_id' => $property,
            'week_start' => $week->copy()->addWeeks($i)->toDateString(),
            'week_end' => $week->copy()->addWeeks($i)->addDays(6)->toDateString(),
            'status' => PayrollPeriodStatus::Open,
        ]);
    }

    $allocate->handle($schedule->fresh());

    expect($schedule->fresh()->entries->sum('amount'))->toBe(250_00);
});

it('never parks a payment on a locked period, which would strand it', function () {
    $person = placedContractor(weeks: 0);
    $property = $person->workOrders()->first()->property_id;

    // A locked period has a submitted timesheet; the apply job only processes
    // open ones, so an entry here would never apply while still counting as
    // allocated.
    PayrollPeriod::factory()->create([
        'property_id' => $property,
        'week_start' => now()->startOfWeek()->toDateString(),
        'week_end' => now()->startOfWeek()->addDays(6)->toDateString(),
        'status' => PayrollPeriodStatus::Locked,
    ]);

    $schedule = app(ScheduleHiringFee::class)->handle($person);
    app(AllocateChargeScheduleEntries::class)->handle($schedule);

    expect($schedule->fresh()->entries)->toHaveCount(0);
});

it('is idempotent — running the allocator again adds nothing', function () {
    $schedule = app(ScheduleHiringFee::class)->handle(placedContractor());
    $allocate = app(AllocateChargeScheduleEntries::class);

    $allocate->handle($schedule);
    $second = $allocate->handle($schedule->fresh());

    expect($second)->toBe(0)
        ->and($schedule->fresh()->entries)->toHaveCount(13);
});

it('charges a contractor the fee only once, including on rehire', function () {
    $person = placedContractor();
    $action = app(ScheduleHiringFee::class);

    $first = $action->handle($person);
    $second = $action->handle($person);

    expect($second->id)->toBe($first->id)
        ->and(ContractorChargeSchedule::where('person_id', $person->id)->count())->toBe(1);
});

it('lets an office manager change the pace without touching what is already collected', function () {
    $person = placedContractor();
    $schedule = app(ScheduleHiringFee::class)->handle($person);
    app(AllocateChargeScheduleEntries::class)->handle($schedule);

    // Two payments have already come out of pay.
    $schedule->entries()->limit(2)->update(['status' => ChargeEntryStatus::Applied->value]);

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/contractor-charges/{$schedule->id}"), ['amount_per_payment' => 50_00])
        ->assertSessionHasNoErrors();

    $schedule->refresh();
    $applied = $schedule->entries()->where('status', ChargeEntryStatus::Applied->value)->get();
    $scheduled = $schedule->entries()->where('status', ChargeEntryStatus::Scheduled->value)->get();

    expect($schedule->amount_per_payment)->toBe(50_00)
        ->and($applied->sum('amount'))->toBe(40_00)          // untouched
        ->and($scheduled->sum('amount'))->toBe(210_00)       // the rest, at the new pace
        ->and($scheduled->first()->amount)->toBe(50_00);
});

it('stops collecting when cancelled but keeps what was already taken', function () {
    $person = placedContractor();
    $schedule = app(ScheduleHiringFee::class)->handle($person);
    app(AllocateChargeScheduleEntries::class)->handle($schedule);
    $schedule->entries()->limit(3)->update(['status' => ChargeEntryStatus::Applied->value]);

    $this->actingAs(person('office_manager'))
        ->delete(main("/admin/contractor-charges/{$schedule->id}"))
        ->assertSessionHasNoErrors();

    $schedule->refresh();

    expect($schedule->status)->toBe(ChargeScheduleStatus::Cancelled)
        ->and($schedule->entries()->where('status', ChargeEntryStatus::Scheduled->value)->count())->toBe(0)
        ->and($schedule->entries()->where('status', ChargeEntryStatus::Applied->value)->sum('amount'))->toBe(60_00);
});

it('stays active until the whole fee is collected, not just the allocated part', function () {
    // Only four periods exist, so the fee cannot be allocated in one go.
    $person = placedContractor(weeks: 4);
    $schedule = app(ScheduleHiringFee::class)->handle($person);

    (new ApplyScheduledContractorCharges)->handle();

    $schedule->refresh();

    // Retiring it here would strand the rest: the allocator only tops up
    // active schedules, so it would never be collected.
    expect($schedule->entries()->where('status', ChargeEntryStatus::Applied->value)->sum('amount'))->toBe(80_00)
        ->and($schedule->status)->toBe(ChargeScheduleStatus::Active);
});

it('lets the contractor’s own recruiter change the pace', function () {
    $person = placedContractor();
    $recruiter = person('recruiter');
    $person->update(['primary_recruiter_id' => $recruiter->id]);

    $schedule = app(ScheduleHiringFee::class)->handle($person);

    $this->actingAs($recruiter)
        ->patch(main("/admin/contractor-charges/{$schedule->id}"), ['amount_per_payment' => 35_00])
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->amount_per_payment)->toBe(35_00);
});

it('keeps a recruiter out of a schedule for someone else’s contractor', function () {
    // Recruiters hold time_entries.add_adjustment but are scoped to their own
    // contractors (ADR-0019) — the permission alone must not grant reach.
    $person = placedContractor();
    $person->update(['primary_recruiter_id' => person('recruiter')->id]);

    $schedule = app(ScheduleHiringFee::class)->handle($person);
    $otherRecruiter = person('recruiter');

    $this->actingAs($otherRecruiter)
        ->patch(main("/admin/contractor-charges/{$schedule->id}"), ['amount_per_payment' => 99_00])
        ->assertForbidden();

    $this->actingAs($otherRecruiter)
        ->delete(main("/admin/contractor-charges/{$schedule->id}"))
        ->assertForbidden();

    expect($schedule->fresh()->amount_per_payment)->toBe(20_00);
});

it('does not schedule anything when the fee is configured to zero', function () {
    config()->set('qcp.hiring_fee.amount_cents', 0);

    expect(app(ScheduleHiringFee::class)->handle(placedContractor()))->toBeNull();
});
