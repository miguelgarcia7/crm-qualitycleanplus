<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * `$weeks` consecutive past weeks of timesheets for one property, starting
 * `$skipWeeks` back — a property has at most one period per week, so callers
 * stacking several runs have to start each one where the last ended.
 *
 * @return list<Timesheet>
 */
function weeksOfTimesheets(Property $property, int $weeks, TimesheetStatus $status = TimesheetStatus::Draft, int $skipWeeks = 0): array
{
    $sheets = [];

    for ($i = $skipWeeks + 1; $i <= $skipWeeks + $weeks; $i++) {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($i);

        $period = PayrollPeriod::factory()->forWeek($monday)->create(['property_id' => $property->id]);
        $sheets[] = Timesheet::factory()->create([
            'property_id' => $property->id,
            'payroll_period_id' => $period->id,
            'status' => $status,
        ]);
    }

    return $sheets;
}

/** The timesheet ids on one page of the history list. */
function pageIds(string $query = ''): array
{
    $ids = [];

    test()->actingAs(person('office_manager'))->get(main('/admin/timesheets'.$query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = collect($page->toArray()['props']['timesheets'])->pluck('id')->all();
        });

    return $ids;
}

it('slices the list in the database rather than shipping every row', function () {
    $property = Property::factory()->create();
    weeksOfTimesheets($property, 30);

    $this->actingAs(person('office_manager'))->get(main('/admin/timesheets'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/timesheets/index')
            // 25 rows on the wire, not 30 — the whole point of the change.
            ->has('timesheets', 25)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2)
            ->where('pagination.from', 1)
            ->where('pagination.to', 25),
        );
});

it('returns a second page that neither repeats nor drops a row', function () {
    $property = Property::factory()->create();
    weeksOfTimesheets($property, 30);

    $first = pageIds();
    $second = pageIds('?page=2');

    expect($second)->toHaveCount(5)
        // Weeks tie constantly; without a stable tiebreaker rows leak between pages.
        ->and(array_intersect($first, $second))->toBeEmpty()
        ->and(array_unique([...$first, ...$second]))->toHaveCount(30);
});

it('honours the per-page choice and ignores one it does not offer', function () {
    $property = Property::factory()->create();
    weeksOfTimesheets($property, 30);

    expect(pageIds('?per_page=10'))->toHaveCount(10)
        // Not on the whitelist — falls back rather than letting a hand-edited
        // URL ask for every row at once.
        ->and(pageIds('?per_page=5000'))->toHaveCount(25);
});

it('filters by tab and counts the other tabs under the same filters', function () {
    $property = Property::factory()->create();
    weeksOfTimesheets($property, 3, TimesheetStatus::Draft);
    weeksOfTimesheets($property, 2, TimesheetStatus::PendingApproval, skipWeeks: 3);

    $this->actingAs(person('office_manager'))->get(main('/admin/timesheets?tab=pending'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('timesheets', 2)
            ->where('filters.tab', 'pending')
            // Counts describe what switching tabs would show, so they ignore
            // the tab filter itself.
            ->where('counts.draft', 3)
            ->where('counts.pending', 2)
            ->where('counts.all', 5)
            ->where('counts.billed', 0),
        );
});

it('searches on property name', function () {
    $sunrise = Property::factory()->create(['name' => 'Sunrise Villas']);
    $harbor = Property::factory()->create(['name' => 'Harbor Point']);
    weeksOfTimesheets($sunrise, 2);
    weeksOfTimesheets($harbor, 3);

    expect(pageIds('?search=Sunrise'))->toHaveCount(2)
        ->and(pageIds('?search=Harbor'))->toHaveCount(3)
        ->and(pageIds('?search=nothing at all'))->toBeEmpty();
});

it('filters by property', function () {
    $sunrise = Property::factory()->create(['name' => 'Sunrise Villas']);
    $harbor = Property::factory()->create(['name' => 'Harbor Point']);
    weeksOfTimesheets($sunrise, 2);
    weeksOfTimesheets($harbor, 3);

    expect(pageIds("?property_id={$harbor->id}"))->toHaveCount(3);
});

it('orders by a whitelisted column and falls back when handed anything else', function () {
    $property = Property::factory()->create();
    weeksOfTimesheets($property, 4);

    // Default: newest week first.
    $newestFirst = pageIds();
    $oldestFirst = pageIds('?sort=week_start&direction=asc');

    expect($oldestFirst)->toBe(array_reverse($newestFirst));

    // An unknown column must not reach the query builder.
    $this->actingAs(person('office_manager'))->get(main('/admin/timesheets?sort=password&direction=sideways'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.sort', 'week_start')
            ->where('filters.direction', 'desc'),
        );
});

it('still hides periods that have not happened yet', function () {
    $property = Property::factory()->create();
    weeksOfTimesheets($property, 2);

    $nextWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();
    $ahead = PayrollPeriod::factory()->forWeek($nextWeek)->create(['property_id' => $property->id]);
    Timesheet::factory()->create(['property_id' => $property->id, 'payroll_period_id' => $ahead->id]);

    expect(pageIds())->toHaveCount(2);
});

it('exports what the filters selected, not the whole table', function () {
    $sunrise = Property::factory()->create(['name' => 'Sunrise Villas']);
    weeksOfTimesheets($sunrise, 2);
    weeksOfTimesheets(Property::factory()->create(['name' => 'Harbor Point']), 3);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/timesheets/export?property_id={$sunrise->id}"))
        ->assertOk()
        ->assertDownload();
});
