<?php

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('computes week starts from the property closing day', function () {
    $sundayClose = Property::factory()->create(['closing_day' => null]);
    $wednesdayClose = Property::factory()->create(['closing_day' => 3]);

    // 2026-06-10 is a Wednesday.
    expect($sundayClose->weekStartFor('2026-06-10')->toDateString())->toBe('2026-06-08')      // Monday
        ->and($wednesdayClose->weekStartFor('2026-06-10')->toDateString())->toBe('2026-06-04') // prior Thursday
        ->and($wednesdayClose->weekStartFor('2026-06-11')->toDateString())->toBe('2026-06-11') // Thursday starts a new week
        ->and($sundayClose->weekStartFor('2026-06-08')->toDateString())->toBe('2026-06-08');   // Monday is its own start
});

it('materializes periods anchored on the closing day', function () {
    $property = Property::factory()->create(['closing_day' => 3, 'timezone' => 'America/Chicago']);

    $this->artisan('payroll:ensure-periods')->assertSuccessful();

    $periods = PayrollPeriod::query()->where('property_id', $property->id)->orderBy('week_start')->get();
    $today = CarbonImmutable::now('America/Chicago')->toDateString();

    expect($periods)->not->toBeEmpty();
    foreach ($periods as $period) {
        expect($period->week_start->dayOfWeekIso)->toBe(4)   // Thursday
            ->and($period->week_end->dayOfWeekIso)->toBe(3); // Wednesday
    }
    expect($periods->contains(fn (PayrollPeriod $p) => $p->week_start->toDateString() <= $today && $p->week_end->toDateString() >= $today))->toBeTrue();
});

it('attaches entries to the property-anchored period', function () {
    $property = Property::factory()->create(['closing_day' => 3, 'timezone' => 'America/Chicago']);
    $workOrder = WorkOrder::factory()->create(['property_id' => $property->id]);

    $this->artisan('payroll:ensure-periods');

    $today = CarbonImmutable::now('America/Chicago');
    $entry = app(CreateManualTimeEntry::class)->handle($workOrder, [
        'date' => $today->toDateString(),
        'start_time' => '09:00',
        'end_time' => '17:00',
        'entry_type' => 'work',
    ], null);

    expect($entry->payrollPeriod->week_start->toDateString())
        ->toBe($property->weekStartFor($today)->toDateString())
        ->and($entry->payrollPeriod->week_start->dayOfWeekIso)->toBe(4);
});

it('shows the grid anchored on the property week', function () {
    $property = Property::factory()->create(['closing_day' => 3, 'timezone' => 'America/Chicago']);
    $this->artisan('payroll:ensure-periods');

    // Ask for an arbitrary mid-week date — the grid should snap to the
    // property's week start (a Thursday), not to Monday.
    $today = CarbonImmutable::now('America/Chicago');
    $anchored = $property->weekStartFor($today)->toDateString();

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}/grid?week={$today->toDateString()}"))
        ->assertOk()
        ->assertSee($anchored);
});

it('keeps Monday weeks for properties without a closing day', function () {
    $property = Property::factory()->create(['closing_day' => null, 'timezone' => 'America/Chicago']);

    $this->artisan('payroll:ensure-periods');

    $period = PayrollPeriod::query()->where('property_id', $property->id)->orderBy('week_start')->firstOrFail();
    expect($period->week_start->dayOfWeekIso)->toBe(1)   // Monday
        ->and($period->week_end->dayOfWeekIso)->toBe(7); // Sunday
});
