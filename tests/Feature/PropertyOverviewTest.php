<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Devices\Models\Device;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyDepartment;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([RolePermissionSeeder::class, DepartmentSeeder::class]);
    // A Wednesday afternoon in Phoenix, so "this week" is unambiguous.
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));
});

/** A week's period and timesheet, `$weeksAgo` before this week. */
function overviewWeek(Property $property, int $weeksAgo, TimesheetStatus $status = TimesheetStatus::Draft, array $timesheet = []): PayrollPeriod
{
    $period = PayrollPeriod::factory()
        ->forWeek(Carbon::parse('2026-10-05')->subWeeks($weeksAgo))
        ->create(['property_id' => $property->id]);

    Timesheet::factory()->create(['property_id' => $property->id, 'payroll_period_id' => $period->id, 'status' => $status, ...$timesheet]);

    return $period;
}

function overviewSummary(PayrollPeriod $period, array $minutes): TimeSummary
{
    return TimeSummary::factory()->create([
        'property_id' => $period->property_id,
        'payroll_period_id' => $period->id,
        'week_start' => $period->week_start,
        'week_end' => $period->week_end,
        'regular_minutes' => 0,
        'overtime_minutes' => 0,
        'holiday_minutes' => 0,
        'training_minutes' => 0,
        'total_pay' => 0,
        'total_bill' => 0,
        ...$minutes,
    ]);
}

it('totals this week from the time summaries', function () {
    $property = Property::factory()->create();
    $period = overviewWeek($property, 0);
    overviewSummary($period, ['regular_minutes' => 2400, 'overtime_minutes' => 60, 'total_pay' => 70000, 'total_bill' => 110000]);
    overviewSummary($period, ['regular_minutes' => 600, 'total_pay' => 10000, 'total_bill' => 16000]);
    // An open punch from Monday: never clocked out.
    TimeEntry::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'start_at_utc' => '2026-10-05 15:00:00',
        'end_at_utc' => null,
    ]);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/properties/show')
            ->where('overview.thisWeek.week_start', '2026-10-05')
            ->where('overview.thisWeek.status', 'draft')
            ->where('overview.thisWeek.people', 2)
            ->where('overview.thisWeek.total_minutes', 3060)
            ->where('overview.thisWeek.overtime_minutes', 60)
            ->where('overview.thisWeek.total_pay', 80000)
            ->where('overview.thisWeek.total_bill', 126000)
            ->where('overview.thisWeek.needs_look', 1),
        );
});

it('lists the latest started weeks newest first, leaving out weeks ahead', function () {
    $property = Property::factory()->create();
    overviewWeek($property, -1); // next week, made ahead for manual entry
    foreach (range(0, 7) as $weeksAgo) {
        overviewWeek($property, $weeksAgo);
    }

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.weeks', 6)
            ->where('overview.weeks.0.week_start', '2026-10-05')
            ->where('overview.weeks.5.week_start', '2026-08-31'),
        );
});

it('flags weeks waiting on approval and weeks sent back', function () {
    $property = Property::factory()->create();
    overviewWeek($property, 1, TimesheetStatus::PendingApproval, ['sent_for_approval_at' => '2026-10-05 14:30:00']);
    overviewWeek($property, 2, TimesheetStatus::Declined, ['decline_reason' => 'Maria worked Tuesday, not Monday']);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(function (Assert $page) use ($property) {
            $items = collect($page->toArray()['props']['overview']['attention'])->keyBy('title');

            expect($items->get('Week of Sep 28 is waiting on approval'))
                ->not->toBeNull()
                ->and($items->get('Week of Sep 28 is waiting on approval')['action']['href'])->toBe("/admin/properties/{$property->id}/grid?week=2026-09-28")
                ->and($items->get('Week of Sep 21 was declined')['detail'])->toBe('Maria worked Tuesday, not Monday');
        });
});

it('flags gaps in the property setup', function () {
    $property = Property::factory()->create(['latitude' => null, 'longitude' => null]);
    $tablet = Device::factory()->create(['property_id' => $property->id, 'name' => 'Lobby Tablet', 'activation_code' => 'ACME01']);

    $this->actingAs(person('super_admin'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(function (Assert $page) use ($tablet) {
            $items = collect($page->toArray()['props']['overview']['attention'])->keyBy('key');

            expect($items->keys()->sort()->values()->all())->toBe(['contract', 'departments', "device-{$tablet->id}", 'geofence', 'rates'])
                ->and($items["device-{$tablet->id}"]['title'])->toBe('Lobby Tablet is not activated')
                ->and($items['departments']['action'])->toBe(['label' => 'Contacts', 'tab' => 'settings', 'section' => 'contacts']);
        });
});

it('stays quiet once the setup is complete', function () {
    $property = Property::factory()->create();
    PropertyPositionRate::factory()->create(['property_id' => $property->id]);
    PropertyDepartment::factory()->create(['property_id' => $property->id]);
    Contract::factory()->create(['property_id' => $property->id]);
    Device::factory()->create(['property_id' => $property->id, 'is_activated' => true]);

    $this->actingAs(person('super_admin'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('overview.attention', []));
});

it('warns when a contract is about to expire', function () {
    $property = Property::factory()->create();
    Contract::factory()->create(['property_id' => $property->id, 'name' => 'Acme MSA', 'expiration_date' => '2026-10-20']);

    $this->actingAs(person('super_admin'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.attention', fn ($items) => collect($items)->contains('title', 'Acme MSA expires Oct 20')),
        );
});

it('shows HR the property without its hours, money or contracts', function () {
    $property = Property::factory()->create();
    overviewWeek($property, 1, TimesheetStatus::PendingApproval, ['sent_for_approval_at' => '2026-10-05 14:30:00']);

    $this->actingAs(person('hr'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.thisWeek', null)
            ->where('overview.weeks', null)
            ->where('can.viewGrid', false)
            ->where('overview.attention', fn ($items) => collect($items)->pluck('key')->all() === ['departments']),
        );
});
