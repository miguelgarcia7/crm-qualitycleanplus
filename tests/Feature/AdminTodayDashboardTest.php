<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Services\AdminToday;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // Wednesday noon in Chicago, so "last week" and "this week" are unambiguous.
    $this->travelTo(CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'));
});

function adminToday(): array
{
    return app(AdminToday::class)->build(person('admin'));
}

/** A property's pay week starting $weekStart, with its timesheet. */
function weekSheet(Property $property, string $weekStart, TimesheetStatus $status, ?CarbonImmutable $sentAt = null): Timesheet
{
    $start = CarbonImmutable::parse($weekStart);

    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => $start->toDateString(),
        'week_end' => $start->addDays(6)->toDateString(),
    ]);

    return Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'status' => $status,
        'sent_for_approval_at' => $sentAt,
    ]);
}

it('lands admins on the Today view and keeps the other roles on their widgets', function () {
    $this->actingAs(person('admin'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/admin-today')->has('today.pipeline'));

    $this->actingAs(person('super_admin'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/admin-today'));

    $this->actingAs(person('hr'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/index'));
});

it('highlights the approval step once a timesheet is past three days, and lists who to chase oldest first', function () {
    $acme = Property::factory()->create(['name' => 'Acme Hotel']);
    $harbor = Property::factory()->create(['name' => 'Harbor Point']);
    $acme->assignments()->create(['person_id' => tap(person('property_manager'))->update(['name' => 'Pat Manager'])->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

    weekSheet($harbor, '2026-10-05', TimesheetStatus::PendingApproval, CarbonImmutable::now()->subDay());
    weekSheet($acme, '2026-09-28', TimesheetStatus::PendingApproval, CarbonImmutable::now()->subDays(5));

    $pipeline = adminToday()['pipeline'];
    $awaiting = collect($pipeline['stages'])->firstWhere('key', 'awaiting');

    expect($awaiting['value'])->toBe('2')
        ->and($awaiting['stuck'])->toBeTrue()
        ->and($awaiting['note'])->toBe('1 past 3 days · list below')
        ->and($pipeline['chaseTotal'])->toBe(2)
        ->and(collect($pipeline['chase'])->pluck('property')->all())->toBe(['Acme Hotel', 'Harbor Point'])
        ->and($pipeline['chase'][0]['manager'])->toBe('Pat Manager')
        ->and($pipeline['chase'][0]['overdue'])->toBeTrue()
        ->and($pipeline['chase'][0]['href'])->toBe("/admin/properties/{$acme->id}/grid?week=2026-09-28")
        ->and($pipeline['chase'][1]['overdue'])->toBeFalse();
});

it('caps the chase list at five and keeps the full count for View all', function () {
    foreach (range(1, 7) as $i) {
        weekSheet(Property::factory()->create(), '2026-10-05', TimesheetStatus::PendingApproval, CarbonImmutable::now()->subHours($i));
    }

    $pipeline = adminToday()['pipeline'];

    expect($pipeline['chase'])->toHaveCount(5)
        ->and($pipeline['chaseTotal'])->toBe(7)
        ->and(collect($pipeline['stages'])->firstWhere('key', 'awaiting')['stuck'])->toBeFalse();
});

it('counts last week as submitted per property that logged hours', function () {
    [$sent, $draft, $idle] = Property::factory()->count(3)->create();

    foreach ([$sent, $draft] as $property) {
        $sheet = weekSheet($property, '2026-10-05', $property->is($sent) ? TimesheetStatus::PendingApproval : TimesheetStatus::Draft, CarbonImmutable::now());
        TimeSummary::factory()->create([
            'property_id' => $property->id,
            'payroll_period_id' => $sheet->payroll_period_id,
            'week_start' => '2026-10-05',
            'week_end' => '2026-10-11',
            'regular_minutes' => 600,
        ]);
    }
    weekSheet($idle, '2026-10-05', TimesheetStatus::Draft); // no hours, so not expected to send

    $submitted = collect(adminToday()['pipeline']['stages'])->firstWhere('key', 'submitted');

    expect($submitted['value'])->toBe('1 of 2')
        ->and($submitted['note'])->toBe('1 property not sent yet');
});

it('puts every request needing a decision in one list, oldest first, with applications as one row', function () {
    MoreStaffRequest::factory()->create(['created_at' => now()->subDays(3)]);
    PtoRequest::factory()->create(['submitted_at' => now()->subDay()]);
    JobApplication::factory()->count(2)->create(['submitted_at' => now()->subDays(5)]);

    $decisions = adminToday()['decisions'];

    expect(collect($decisions['rows'])->pluck('type')->all())->toBe(['Applications', 'Staffing', 'Time off'])
        ->and($decisions['rows'][0]['title'])->toBe('2 applications to review')
        ->and($decisions['rows'][0]['age'])->toBe('5 days')
        ->and($decisions['total'])->toBe(4)
        ->and($decisions['hidden'])->toBe(0);
});

it('says how many requests are off screen once the list is full', function () {
    PtoRequest::factory()->count(6)->create();

    $decisions = adminToday()['decisions'];

    expect($decisions['rows'])->toHaveCount(5)
        ->and($decisions['hidden'])->toBe(1)
        ->and($decisions['byType'])->toBe([['label' => 'Time off', 'count' => 6, 'href' => '/admin/pto']]);
});

it('lists contracts ending in the next 30 days, most urgent in red', function () {
    Contract::factory()->expiringInDays(10)->create(['name' => 'Soon']);
    Contract::factory()->expiringInDays(25)->create(['name' => 'Later']);
    Contract::factory()->expiringInDays(45)->create(['name' => 'Not yet']);

    $expiring = adminToday()['expiring'];

    expect($expiring['total'])->toBe(2)
        ->and($expiring['rows'][0]['days'])->toBe(10)
        ->and($expiring['rows'][0]['tone'])->toBe('danger')
        ->and($expiring['rows'][1]['tone'])->toBe('gray');
});

it('shows placements starting and staff away in the next two weeks, by day', function () {
    $property = Property::factory()->create(['name' => 'Lakeside Suites']);
    WorkOrder::factory()->count(2)->create(['property_id' => $property->id, 'start_date' => '2026-10-19']);
    WorkOrder::factory()->create(['start_date' => '2026-11-20']); // too far out
    PtoRequest::factory()->create([
        'person_id' => tap(person('recruiter'))->update(['name' => 'Rita Recruiter'])->id,
        'status' => PtoRequestStatus::Approved,
        'start_date' => '2026-10-16',
        'end_date' => '2026-10-20',
    ]);

    $rows = adminToday()['comingUp']['rows'];

    expect(collect($rows)->pluck('title')->all())->toBe(['Rita Recruiter away', '2 placements start'])
        ->and($rows[1]['detail'])->toBe('Lakeside Suites')
        ->and($rows[1]['day'])->toBe('Oct 19');
});

it('leaves stalled weeks and expiring contracts out of property setup gaps', function () {
    $property = Property::factory()->create();
    $before = collect(adminToday()['setupGaps']['rows'])->firstWhere('id', $property->id)['count'];

    weekSheet($property, '2026-09-28', TimesheetStatus::PendingApproval, CarbonImmutable::now()->subDays(6));

    expect(collect(adminToday()['setupGaps']['rows'])->firstWhere('id', $property->id)['count'])->toBe($before);
});
