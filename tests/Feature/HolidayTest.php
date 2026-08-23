<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Enums\HolidayType;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Support\HolidayDateResolver;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

// --- Date resolution ---------------------------------------------------------

it('resolves legal holiday rules in the given timezone', function () {
    $this->seed(HolidaySeeder::class);
    $tz = 'America/Chicago';
    $byRule = fn (string $slug) => HolidayDateResolver::resolve(Holiday::firstWhere('slug', $slug), 2026, $tz);

    expect($byRule('new_years_day')->toDateString())->toBe('2026-01-01')
        ->and($byRule('memorial_day')->toDateString())->toBe('2026-05-25')       // last Monday of May
        ->and($byRule('labor_day')->toDateString())->toBe('2026-09-07')          // first Monday of September
        ->and($byRule('thanksgiving_day')->toDateString())->toBe('2026-11-26')   // 4th Thursday of November
        ->and($byRule('christmas_day')->toDateString())->toBe('2026-12-25')
        ->and($byRule('thanksgiving_day')->timezoneName)->toBe($tz);
});

it('resolves a custom holiday to its fixed date each year', function () {
    $holiday = Holiday::factory()->create(['month' => 3, 'day' => 15]);

    expect(HolidayDateResolver::resolve($holiday, 2027, 'UTC')->toDateString())->toBe('2027-03-15');
});

// --- Catalog -----------------------------------------------------------------

it('lets an office manager create a custom holiday', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/holidays'), ['name' => 'Founders Day', 'month' => 6, 'day' => 12])
        ->assertRedirect()
        ->assertSessionHas('success');

    $holiday = Holiday::firstWhere('name', 'Founders Day');
    expect($holiday->type)->toBe(HolidayType::Custom)
        ->and($holiday->slug)->toBe('founders_day');
});

it('rejects an impossible custom date like February 30', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/holidays'), ['name' => 'Bad Day', 'month' => 2, 'day' => 30])
        ->assertSessionHasErrors('day');
});

it('refuses to delete a legal holiday but deletes a custom one', function () {
    $this->seed(HolidaySeeder::class);
    $legal = Holiday::firstWhere('slug', 'thanksgiving_day');
    $custom = Holiday::factory()->create();

    $this->actingAs(person('office_manager'))
        ->delete(main("/admin/holidays/{$legal->id}"))
        ->assertSessionHas('error');
    expect(Holiday::find($legal->id))->not->toBeNull();

    $this->actingAs(person('office_manager'))
        ->delete(main("/admin/holidays/{$custom->id}"))
        ->assertSessionHas('success');
    expect(Holiday::find($custom->id))->toBeNull();
});

it('lets a recruiter view the calendar but not change it', function () {
    $this->actingAs(person('recruiter'))->get(main('/admin/holidays'))->assertOk();

    $this->actingAs(person('recruiter'))
        ->post(main('/admin/holidays'), ['name' => 'Sneaky Day', 'month' => 1, 'day' => 2])
        ->assertForbidden();
});

// --- Property opt-in ---------------------------------------------------------

it('attaches the default holidays to a newly created property', function () {
    $this->seed(HolidaySeeder::class);

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/properties'), [
            'name' => 'Holiday Test Hotel',
            'timezone' => 'America/Chicago',
            'geofence_radius_meters' => 300,
            'tax_rate' => 0.08,
            'status' => 'active',
            'time_source' => 'clock_in',
        ])
        ->assertRedirect();

    $property = Property::firstWhere('name', 'Holiday Test Hotel');
    expect($property->holidays()->pluck('slug')->sort()->values()->all())
        ->toBe(collect(Holiday::DEFAULT_ENABLED_SLUGS)->sort()->values()->all());
});

it('syncs a property\'s observed holidays in one request', function () {
    $this->seed(HolidaySeeder::class);
    $property = Property::factory()->create();
    $ids = Holiday::query()->whereIn('slug', ['christmas_eve', 'independence_day'])->pluck('id')->all();

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}/holidays"), ['holiday_ids' => $ids])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($property->holidays()->pluck('holidays.id')->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all());
});

it('forbids a recruiter from changing a property\'s holidays', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('recruiter'))
        ->put(main("/admin/properties/{$property->id}/holidays"), ['holiday_ids' => []])
        ->assertForbidden();
});

// --- Bucketing ---------------------------------------------------------------

/**
 * Property + WO + open period for this week, with a custom holiday attached on
 * this week's Wednesday.
 *
 * @return array{property: Property, workOrder: WorkOrder, monday: Carbon, holidayDay: Carbon}
 */
function holidayScenario(): array
{
    $property = Property::factory()->create(['timezone' => 'America/Phoenix']);
    $workOrder = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
    ]);

    $monday = Carbon::now('America/Phoenix')->startOfWeek(Carbon::MONDAY);
    $holidayDay = $monday->copy()->addDays(2); // Wednesday

    $holiday = Holiday::factory()->create([
        'month' => $holidayDay->month,
        'day' => $holidayDay->day,
    ]);
    $property->holidays()->attach($holiday->id);

    PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(),
        'status' => PayrollPeriodStatus::Open,
    ]);
    Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => PayrollPeriod::first()->id,
        'status' => TimesheetStatus::Draft,
    ]);

    return compact('property', 'workOrder', 'monday', 'holidayDay');
}

function holidayHours(WorkOrder $wo, Carbon $day, string $start, string $end): void
{
    app(CreateManualTimeEntry::class)->handle($wo, [
        'date' => $day->toDateString(),
        'start_time' => $start,
        'end_time' => $end,
        'entry_type' => 'work',
    ], null);
}

it('buckets holiday-day work as holiday time at the multiplier, never overtime', function () {
    ['workOrder' => $wo, 'monday' => $monday, 'holidayDay' => $holidayDay] = holidayScenario();

    holidayHours($wo, $monday, '08:00', '16:00');                    // Mon 8h regular
    holidayHours($wo, $holidayDay, '08:00', '16:00');                // Wed (holiday) 8h

    $summary = TimeSummary::where('work_order_id', $wo->id)->first();

    expect($summary->regular_minutes)->toBe(480)
        ->and($summary->overtime_minutes)->toBe(0)
        ->and($summary->holiday_minutes)->toBe(480)
        ->and($summary->holiday_amount_pay)->toBe(24000)   // 8h × $30 (1.5 × $20)
        ->and($summary->holiday_amount_bill)->toBe(36000)  // 8h × $45 (1.5 × $30)
        ->and($summary->total_pay)->toBe(16000 + 24000)    // 8h × $20 regular + 8h × $30 holiday
        ->and($summary->total_bill)->toBe(24000 + 36000);  // 8h × $30 regular + 8h × $45 holiday
});

it('holiday hours advance the weekly counter and push later days into overtime', function () {
    ['workOrder' => $wo, 'monday' => $monday, 'holidayDay' => $holidayDay] = holidayScenario();

    holidayHours($wo, $monday, '04:00', '20:00');                       // Mon 16h
    holidayHours($wo, $monday->copy()->addDay(), '04:00', '20:00');     // Tue 16h
    holidayHours($wo, $holidayDay, '08:00', '16:00');                   // Wed (holiday) 8h — counter at 40h
    holidayHours($wo, $monday->copy()->addDays(3), '08:00', '13:00');   // Thu 5h — all OT

    $summary = TimeSummary::where('work_order_id', $wo->id)->first();

    expect($summary->regular_minutes)->toBe(1920)      // Mon+Tue
        ->and($summary->holiday_minutes)->toBe(480)    // Wed, never OT
        ->and($summary->overtime_minutes)->toBe(300);  // Thu entirely OT
});

it('work on a holiday past the 40h threshold still buckets as holiday, not overtime', function () {
    ['workOrder' => $wo, 'monday' => $monday, 'holidayDay' => $holidayDay] = holidayScenario();

    holidayHours($wo, $monday, '00:00', '23:00');                      // Mon 23h
    holidayHours($wo, $monday->copy()->addDay(), '00:00', '23:00');    // Tue 23h — 46h, past threshold
    holidayHours($wo, $holidayDay, '08:00', '16:00');                  // Wed (holiday) 8h

    $summary = TimeSummary::where('work_order_id', $wo->id)->first();

    expect($summary->regular_minutes)->toBe(2400)
        ->and($summary->overtime_minutes)->toBe(360)
        ->and($summary->holiday_minutes)->toBe(480);
});

it('recomputes open weeks when a property\'s holiday set changes', function () {
    ['property' => $property, 'workOrder' => $wo, 'holidayDay' => $holidayDay] = holidayScenario();

    holidayHours($wo, $holidayDay, '08:00', '16:00');

    expect(TimeSummary::where('work_order_id', $wo->id)->first()->holiday_minutes)->toBe(480);

    // Un-observe every holiday: the same hours become regular time.
    $this->actingAs(person('office_manager'))
        ->put(main("/admin/properties/{$property->id}/holidays"), ['holiday_ids' => []])
        ->assertRedirect();

    $summary = TimeSummary::where('work_order_id', $wo->id)->first();
    expect($summary->holiday_minutes)->toBe(0)
        ->and($summary->regular_minutes)->toBe(480);
});
