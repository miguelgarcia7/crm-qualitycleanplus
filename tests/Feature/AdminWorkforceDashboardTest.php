<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Services\AdminWorkforce;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // Wednesday Oct 14, noon in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'));
});

function workforce(): array
{
    return app(AdminWorkforce::class)->build();
}

it('adds Workforce as the third tab', function () {
    $this->actingAs(person('admin'))->get(main('/admin/dashboard?view=workforce'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/dashboard/admin-workforce')
            ->where('view', 'workforce')
            ->where('tabs.2.key', 'workforce')
            ->has('workforce.headline', 4));
});

it('counts active contractors, placements this month, who is on the clock and who is still to place', function () {
    WorkOrder::factory()->create(['start_date' => '2026-10-05']);
    WorkOrder::factory()->create(['start_date' => '2026-05-01', 'end_date' => '2026-10-09']);
    WorkOrder::factory()->create(['start_date' => '2026-05-01']);

    $site = Property::factory()->create();
    TimeEntry::factory()->count(2)->create(['property_id' => $site->id, 'start_at_utc' => now()->subHours(2), 'end_at_utc' => null, 'duration_minutes' => null]);

    MoreStaffRequest::factory()->create(['quantity_requested' => 3, 'quantity_fulfilled' => 1]);
    MoreStaffRequest::factory()->create(['status' => MoreStaffStatus::Fulfilled]);

    $stats = collect(workforce()['headline'])->keyBy('key');

    expect($stats['active']['value'])->toBe(Person::query()->where('status', PersonStatus::ContractorActive->value)->count())
        ->and($stats['active']['note'])->toBe('+1 placed, −1 ended this month')
        ->and($stats['clock']['value'])->toBe(2)
        ->and($stats['clock']['note'])->toBe('at 1 property')
        ->and($stats['staffing']['value'])->toBe(1)
        ->and($stats['staffing']['note'])->toBe('2 contractors still to place');
});

it('ranks properties by contractors placed and says where more are needed', function () {
    $acme = Property::factory()->create(['name' => 'Acme Hotel']);
    $lakeside = Property::factory()->create(['name' => 'Lakeside Suites']);
    WorkOrder::factory()->count(3)->create(['property_id' => $acme->id]);
    WorkOrder::factory()->create(['property_id' => $lakeside->id]);
    MoreStaffRequest::factory()->create(['property_id' => $lakeside->id, 'quantity_requested' => 2]);

    $rows = collect(workforce()['byProperty']['rows'])->whereIn('id', [$acme->id, $lakeside->id])->values();

    expect($rows->all())->toBe([
        ['id' => $acme->id, 'name' => 'Acme Hotel', 'contractors' => 3, 'needed' => 0],
        ['id' => $lakeside->id, 'name' => 'Lakeside Suites', 'contractors' => 1, 'needed' => 2],
    ]);
});

it('counts this month of hiring and lists the busiest open postings', function () {
    $posting = JobPosting::factory()->published()->create(['title' => 'Housekeeper']);
    JobApplication::factory()->count(2)->create(['job_posting_id' => $posting->id, 'submitted_at' => now()->subDays(3)]);
    JobApplication::factory()->create(['submitted_at' => '2026-09-20']);
    JobApplication::factory()->reviewing()->create(['submitted_at' => '2026-09-20']);
    JobPosting::factory()->closed()->create();

    $hiring = workforce()['hiring'];

    expect($hiring['applied'])->toBe(2)
        ->and($hiring['inReview'])->toBe(1)
        ->and($hiring['openPostings'])->toBe(1)
        ->and($hiring['postings'][0])->toMatchArray(['title' => 'Housekeeper', 'applied' => 2]);
});

it("sums each recruiter's properties, visits and unsent weeks", function () {
    $ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter']);
    $harbor = Property::factory()->create();
    $harbor->assignments()->create(['person_id' => $ray->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
    WorkOrder::factory()->count(2)->create(['property_id' => $harbor->id]);

    FieldVisit::factory()->create(['person_id' => $ray->id, 'property_id' => $harbor->id, 'check_in_at' => now()->subDay()]);
    FieldVisit::factory()->closed()->create(['person_id' => $ray->id, 'property_id' => $harbor->id, 'check_in_at' => now()->subDays(10)]);

    // A finished week with hours, never sent; and one with no hours at all.
    foreach (['2026-09-28' => 600, '2026-09-21' => 0] as $start => $minutes) {
        $period = PayrollPeriod::factory()->create(['property_id' => $harbor->id, 'week_start' => $start, 'week_end' => CarbonImmutable::parse($start)->addDays(6)->toDateString()]);
        Timesheet::factory()->create(['property_id' => $harbor->id, 'payroll_period_id' => $period->id, 'status' => TimesheetStatus::Draft]);
        TimeSummary::factory()->create(['property_id' => $harbor->id, 'payroll_period_id' => $period->id, 'week_start' => $start, 'regular_minutes' => $minutes]);
    }

    $row = collect(workforce()['recruiters'])->firstWhere('id', $ray->id);

    expect($row)->toBe([
        'id' => $ray->id,
        'name' => 'Ray Recruiter',
        'properties' => 1,
        'contractors' => 2,
        'visits' => 1,
        'openVisits' => 1,
        'notSent' => 1,
    ]);
});

it('lists contractors reaching direct hire within ten weeks at their recent pace', function () {
    $close = WorkOrder::factory()->create(['direct_hire_threshold_minutes' => 2080 * 60]);
    $far = WorkOrder::factory()->create(['direct_hire_threshold_minutes' => 2080 * 60]);

    // 2,000 h so far, the last two finished weeks at 40 h: about two weeks to go.
    foreach ([$close->id => [1920, 40, 40], $far->id => [100, 40, 40]] as $id => [$earlier, $week2, $week1]) {
        $wo = WorkOrder::find($id);
        foreach (['2026-08-03' => $earlier, '2026-09-28' => $week2, '2026-10-05' => $week1] as $start => $hours) {
            TimeSummary::factory()->create([
                'work_order_id' => $wo->id,
                'person_id' => $wo->person_id,
                'property_id' => $wo->property_id,
                'week_start' => $start,
                'week_end' => CarbonImmutable::parse($start)->addDays(6)->toDateString(),
                'regular_minutes' => $hours * 60,
            ]);
        }
    }

    $directHire = workforce()['directHire'];

    expect($directHire['total'])->toBe(1)
        ->and($directHire['rows'][0])->toMatchArray(['id' => $close->id, 'worked' => 2000.0, 'threshold' => 2080.0, 'weeks' => 2]);
});

it('shows approved time off in the next 30 days, flagging who is away now', function () {
    PtoRequest::factory()->create(['status' => PtoRequestStatus::Approved, 'start_date' => '2026-10-13', 'end_date' => '2026-10-15']);
    PtoRequest::factory()->create(['status' => PtoRequestStatus::Approved, 'start_date' => '2026-10-20', 'end_date' => '2026-10-24']);
    PtoRequest::factory()->create(['status' => PtoRequestStatus::Approved, 'start_date' => '2026-12-01', 'end_date' => '2026-12-02']);
    PtoRequest::factory()->create(['status' => PtoRequestStatus::Pending, 'start_date' => '2026-10-21', 'end_date' => '2026-10-21']);

    $away = workforce()['staffAway'];

    expect($away['total'])->toBe(2)
        ->and(collect($away['rows'])->pluck('dates')->all())->toBe(['Oct 13 – 15', 'Oct 20 – 24'])
        ->and(collect($away['rows'])->pluck('now')->all())->toBe([true, false]);
});
