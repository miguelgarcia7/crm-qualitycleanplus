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
            ->where('oldest_pending_days', null)
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

it('shows a recruiter only the timesheets of the properties they are assigned', function () {
    $recruiter = person('recruiter');
    $theirs = Property::factory()->create(['name' => 'Sunrise Villas']);
    $theirs->assignments()->create(['person_id' => $recruiter->id, 'role' => 'recruiter']);
    weeksOfTimesheets($theirs, 2);
    weeksOfTimesheets(Property::factory()->create(['name' => 'Harbor Point']), 3);

    $this->actingAs($recruiter)->get(main('/admin/timesheets'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('timesheets', 2)
            ->where('timesheets.0.property', 'Sunrise Villas')
            ->where('counts.all', 2)
            ->where('properties', [['id' => $theirs->id, 'name' => 'Sunrise Villas']]),
        );

    // An office manager sees every property.
    expect(pageIds())->toHaveCount(5);
});

it('keeps declined and voided apart, and says why a sheet was declined', function () {
    $property = Property::factory()->create();
    [$declined] = weeksOfTimesheets($property, 1, TimesheetStatus::Declined);
    $declined->update(['decline_reason' => 'Maria is missing Tuesday']);
    weeksOfTimesheets($property, 2, TimesheetStatus::Voided, skipWeeks: 1);

    $this->actingAs(person('office_manager'))->get(main('/admin/timesheets?tab=declined'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('timesheets', 1)
            ->where('timesheets.0.decline_reason', 'Maria is missing Tuesday')
            ->where('counts.declined', 1)
            ->where('counts.voided', 2),
        );
});

it('marks the week in progress and how long a pending sheet has waited', function () {
    $this->travelTo(Carbon::parse('2026-10-07 18:00:00', 'UTC'));
    $property = Property::factory()->create(['timezone' => 'America/Chicago']);
    weeksOfTimesheets($property, 1, TimesheetStatus::PendingApproval)[0]
        ->update(['sent_for_approval_at' => now()->subDays(3)->subHour()]);
    $current = PayrollPeriod::factory()->forWeek(Carbon::now()->startOfWeek(Carbon::MONDAY))->create(['property_id' => $property->id]);
    Timesheet::factory()->create(['property_id' => $property->id, 'payroll_period_id' => $current->id]);

    $this->actingAs(person('office_manager'))->get(main('/admin/timesheets'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timesheets.0.is_current', true)
            ->where('timesheets.0.waiting_days', null)
            ->where('timesheets.1.is_current', false)
            ->where('timesheets.1.waiting_days', 3)
            // The Pending card says how long the oldest one has waited.
            ->where('oldest_pending_days', 3),
        );
});
