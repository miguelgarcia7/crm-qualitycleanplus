<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // A Wednesday afternoon in Phoenix, so "this week" is unambiguous.
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));
});

/** The profile's props for $person, as $as sees it. */
function profileProps(Person $person, ?Person $as = null): array
{
    $props = [];

    test()->actingAs($as ?? person('office_manager'))
        ->get(main("/admin/people/{$person->id}"))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    return $props;
}

/** A week of time on a work order: [regular, overtime] minutes. */
function weekOn(WorkOrder $wo, string $monday, int $regular, int $overtime = 0): void
{
    $period = PayrollPeriod::factory()->forWeek(Carbon::parse($monday))->create(['property_id' => $wo->property_id]);
    TimeSummary::factory()->create([
        'work_order_id' => $wo->id, 'person_id' => $wo->person_id, 'property_id' => $wo->property_id,
        'payroll_period_id' => $period->id, 'week_start' => $monday, 'week_end' => Carbon::parse($monday)->addDays(6)->toDateString(),
        'regular_minutes' => $regular, 'overtime_minutes' => $overtime, 'holiday_minutes' => 0, 'training_minutes' => 0,
    ]);
}

it('lists every active placement with its own direct-hire progress, and skips closed ones', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $acme = WorkOrder::factory()->create([
        'person_id' => $contractor->id,
        'property_id' => Property::factory()->create(['name' => 'Acme Hotel'])->id,
        'position_id' => Position::factory()->create(['name' => 'Houseman'])->id,
        'start_date' => '2026-06-12',
        'pay_rate' => 1850,
    ]);
    $qcp = WorkOrder::factory()->create([
        'person_id' => $contractor->id,
        'property_id' => Property::factory()->create(['name' => 'QCP Property'])->id,
        'start_date' => '2026-09-01',
    ]);
    WorkOrder::factory()->create(['person_id' => $contractor->id, 'status' => WorkOrderStatus::Closed]);
    weekOn($acme, '2026-09-21', 2400, 600);
    weekOn($acme, '2026-09-28', 2400, 577);

    $placements = profileProps($contractor)['placements'];

    expect(array_column($placements, 'id'))->toBe([$acme->id, $qcp->id])
        ->and($placements[0])->toMatchArray(['property' => 'Acme Hotel', 'position' => 'Houseman', 'pay_rate' => 1850])
        ->and($placements[0]['direct_hire']['worked_hours'])->toEqual(99.6)
        ->and($placements[0]['weeks_to_go'])->toBeInt()
        ->and($placements[1]['weeks_to_go'])->toBeNull();
});

it('hides rates from a viewer who cannot see them', function () {
    $wo = WorkOrder::factory()->create();

    expect(profileProps($wo->person, person('front_desk'))['placements'][0])
        ->pay_rate->toBeNull()
        ->bill_rate->toBeNull();
});

it('sums the last finished week across placements and leaves the week in progress out', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $a = WorkOrder::factory()->create(['person_id' => $contractor->id]);
    $b = WorkOrder::factory()->create(['person_id' => $contractor->id]);
    weekOn($a, '2026-09-28', 2400, 577);
    weekOn($b, '2026-09-28', 600);
    weekOn($a, '2026-10-05', 1200);

    $props = profileProps($contractor);

    expect($props['lastFullWeek'])->toMatchArray(['week_start' => '2026-09-28', 'total_minutes' => 3577, 'overtime_minutes' => 577])
        ->and(collect($props['recentWeeks'])->where('is_current', true)->pluck('week_start')->all())->toBe(['2026-10-05'])
        ->and($props['recentWeeks'])->toHaveCount(3);
});

it('gives staff an overview without placements, even with no other tab to show', function () {
    $staff = Person::factory()->create(['status' => PersonStatus::StaffActive]);

    // HR sees staff but not the audit log, and this person has no open
    // time-off year: Overview is the only tab, so the page must still render.
    $props = profileProps($staff, person('hr'));

    expect($props['placements'])->toBeNull()
        ->and($props['history'])->toBeNull()
        ->and($props['pto'])->toBeNull()
        ->and($props['can']['pto'])->toBeTrue();
});
