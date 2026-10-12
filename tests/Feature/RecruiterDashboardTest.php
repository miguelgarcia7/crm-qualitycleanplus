<?php

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Services\RecruiterHome;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // Monday Oct 12, 9 am in Chicago: the week of Oct 5 – 11 closed last night.
    $this->travelTo(CarbonImmutable::parse('2026-10-12 14:00:00', 'UTC'));
});

/** A recruiter assigned to the given properties. */
function recruiterFor(Property ...$properties): Person
{
    $recruiter = person('recruiter');
    foreach ($properties as $property) {
        $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
    }

    return $recruiter;
}

/** Last week (Oct 5 – 11) at a property, with its timesheet and logged hours. */
function lastWeekAt(Property $property, TimesheetStatus $status, int $minutes = 600): Timesheet
{
    $period = PayrollPeriod::factory()->create(['property_id' => $property->id, 'week_start' => '2026-10-05', 'week_end' => '2026-10-11']);
    TimeSummary::factory()->create(['property_id' => $property->id, 'payroll_period_id' => $period->id, 'week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'regular_minutes' => $minutes]);

    return Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'status' => $status,
        'sent_for_approval_at' => $status === TimesheetStatus::PendingApproval ? now()->subDays(5) : null,
    ]);
}

it('gives a recruiter their own dashboard, and an admin who also recruits the admin one', function () {
    $recruiter = recruiterFor(Property::factory()->create());

    $this->actingAs($recruiter)->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/recruiter')->where('home.header.properties', 1));

    $admin = tap(person('admin'))->assignRole('recruiter');
    $this->actingAs($admin)->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/admin-today'));
});

it("lists last week's timesheets to fix or submit, and only counts the ones already sent", function () {
    [$qcp, $acme, $sunrise, $idle] = [
        Property::factory()->create(['name' => 'QCP Property']),
        Property::factory()->create(['name' => 'Acme Hotel']),
        Property::factory()->create(['name' => 'Sunrise Villas']),
        Property::factory()->create(['name' => 'Idle Inn']),
    ];
    $recruiter = recruiterFor($qcp, $acme, $sunrise, $idle);

    $qcpSheet = lastWeekAt($qcp, TimesheetStatus::Draft);
    TimeEntry::factory()->create(['property_id' => $qcp->id, 'payroll_period_id' => $qcpSheet->payroll_period_id, 'end_at_utc' => null, 'duration_minutes' => null]);
    $acmeSheet = lastWeekAt($acme, TimesheetStatus::Draft, 35_175);
    lastWeekAt($sunrise, TimesheetStatus::PendingApproval);
    lastWeekAt($idle, TimesheetStatus::Draft, 0); // nothing logged, nothing to send

    $lastWeek = app(RecruiterHome::class)->build($recruiter)['lastWeek'];

    expect($lastWeek['week'])->toBe('Oct 5 – 11')
        ->and($lastWeek['total'])->toBe(3)
        ->and($lastWeek['submitted'])->toBe(1)
        ->and(collect($lastWeek['rows'])->pluck('property')->all())->toBe(['QCP Property', 'Acme Hotel'])
        ->and($lastWeek['rows'][0]['action']['label'])->toBe('Fix punches')
        ->and($lastWeek['rows'][1]['hours'])->toBe(586.25)
        ->and($lastWeek['rows'][1]['action'])->toBe(['label' => 'Submit', 'submitId' => $acmeSheet->id]);
});

it('keeps everything waiting on the recruiter to their own properties', function () {
    $mine = Property::factory()->create(['name' => 'Harbor Point']);
    $theirs = Property::factory()->create();
    $recruiter = recruiterFor($mine);

    MoreStaffRequest::factory()->create(['property_id' => $mine->id, 'created_at' => now()->subDays(4)]);
    MoreStaffRequest::factory()->create(['property_id' => $theirs->id]);
    Invoice::factory()->create(['property_id' => $mine->id, 'status' => InvoiceStatus::Invoiced, 'issue_date' => now()->subDays(2)->toDateString(), 'total' => 4212_40]);
    Invoice::factory()->create(['property_id' => $theirs->id, 'status' => InvoiceStatus::Invoiced]);
    $posting = JobPosting::factory()->published()->create(['property_id' => $mine->id]);
    JobApplication::factory()->create(['job_posting_id' => $posting->id, 'submitted_at' => now()->subDay()]);
    JobApplication::factory()->create(); // a general application, not for their postings

    $waiting = app(RecruiterHome::class)->build($recruiter)['waiting'];

    expect($waiting['total'])->toBe(3)
        ->and(collect($waiting['rows'])->pluck('type')->all())->toBe(['Staffing', 'Invoice', 'Applications'])
        ->and($waiting['rows'][1]['detail'])->toContain('$4,212.40');
});

it('lists the applicants at their postings who are still missing onboarding items', function () {
    $mine = Property::factory()->create();
    $recruiter = recruiterFor($mine);
    $posting = JobPosting::factory()->published()->create(['property_id' => $mine->id]);
    $applicant = Person::factory()->create(['status' => PersonStatus::Applicant]);
    $application = JobApplication::factory()->reviewing()->create(['job_posting_id' => $posting->id, 'person_id' => $applicant->id]);

    $row = collect(app(RecruiterHome::class)->build($recruiter)['waiting']['rows'])->firstWhere('type', 'Onboarding');

    expect($row['title'])->toBe("{$application->person->name} is missing 6 items")
        ->and($row['action']['href'])->toBe("/admin/applicants/{$application->id}");
});

it('shows weeks waiting on their property managers, contracts ending and stale visits', function () {
    [$acme, $cedar] = [Property::factory()->create(['name' => 'Acme Hotel']), Property::factory()->create(['name' => 'Cedar Lodge'])];
    $recruiter = recruiterFor($acme, $cedar);

    lastWeekAt($acme, TimesheetStatus::PendingApproval);
    lastWeekAt(Property::factory()->create(), TimesheetStatus::PendingApproval); // not theirs
    Contract::factory()->expiringInDays(18)->create(['property_id' => $acme->id]);
    FieldVisit::factory()->closed()->create(['person_id' => $recruiter->id, 'property_id' => $acme->id, 'check_in_at' => now()->subDays(20)]);
    FieldVisit::factory()->create(['person_id' => $recruiter->id, 'property_id' => $acme->id, 'check_in_at' => now()->subHour()]);

    $home = app(RecruiterHome::class)->build($recruiter);

    expect($home['managers']['total'])->toBe(1)
        ->and($home['managers']['rows'][0]['overdue'])->toBeTrue()
        ->and($home['expiring']['rows'][0]['days'])->toBe(18)
        ->and(collect($home['visits']['rows'])->pluck('name')->all())->toBe(['Cedar Lodge', 'Acme Hotel'])
        ->and($home['visits']['rows'][0]['days'])->toBeNull()
        ->and($home['visits']['rows'][1]['days'])->toBe(0)
        ->and($home['visits']['thisWeek'])->toBe(1)
        ->and($home['visits']['open'])->toBe(1);
});

it('only counts contractors at the recruiter\'s own properties', function () {
    $mine = Property::factory()->create();
    $recruiter = recruiterFor($mine);
    WorkOrder::factory()->count(2)->create(['property_id' => $mine->id]);
    WorkOrder::factory()->create();

    expect(app(RecruiterHome::class)->build($recruiter)['header']['contractors'])->toBe(2);
});
