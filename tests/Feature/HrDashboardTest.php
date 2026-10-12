<?php

use App\Domain\Dashboards\Services\HrHome;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Pto\Models\PtoYearAllotment;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\Shared\Models\File;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // Monday Oct 12, 9 am in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-10-12 14:00:00', 'UTC'));
});

function hr(): array
{
    return app(HrHome::class)->build(person('hr'));
}

/** An applicant in review whose onboarding is complete except for $missing. */
function reviewing(string $name, array $missing = [], ?JobPosting $posting = null): JobApplication
{
    $person = Person::factory()->create(['name' => $name, 'status' => PersonStatus::Applicant]);
    $file = File::factory()->create(['fileable_type' => $person->getMorphClass(), 'fileable_id' => $person->id])->id;

    $complete = [
        'id_front_file_id' => $file, 'id_front_uploaded_at' => now(),
        'id_back_file_id' => $file, 'id_back_uploaded_at' => now(),
        'i9_file_id' => $file, 'i9_uploaded_at' => now(), 'i9_verified_at' => now(),
        'w9_file_id' => $file, 'w9_uploaded_at' => now(),
        'contractor_agreement_file_id' => $file, 'contractor_agreement_signed_at' => now(),
        'background_check_status' => 'passed', 'background_check_completed_at' => now(),
        'onboarding_waived_items' => [],
    ];
    $person->forceFill([...$complete, ...collect($missing)->mapWithKeys(fn (string $col): array => [$col => null])->all()])->save();

    return JobApplication::factory()->reviewing()->create(['person_id' => $person->id, 'job_posting_id' => $posting?->id, 'reviewed_at' => now()->subDays(3)]);
}

it('gives HR its own dashboard', function () {
    $this->actingAs(person('hr'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/dashboard/hr')->has('home.hiring'));
});

it('splits applicants in review into missing documents and ready to promote', function () {
    $posting = JobPosting::factory()->published()->create(['title' => 'Housekeeper', 'property_id' => Property::factory()->create(['name' => 'Lakeside Suites'])->id]);
    reviewing('Jordan Ellis', [], $posting);
    reviewing('Marcus Hill', ['i9_file_id', 'w9_file_id']);
    JobApplication::factory()->create(); // new, not opened yet

    $home = hr();

    expect($home['hiring'])->toMatchArray(['new' => 1, 'inReview' => 2, 'missingDocs' => 1, 'ready' => 1, 'openPostings' => 1])
        ->and($home['ready']['rows'][0])->toMatchArray(['name' => 'Jordan Ellis', 'role' => 'Housekeeper · Lakeside Suites posting'])
        ->and($home['missing']['rows'][0])->toMatchArray(['name' => 'Marcus Hill', 'missing' => ['I-9', 'W-9']]);
});

it('lists info changes and time off to approve, with what changed and hours left', function () {
    $carlos = Person::factory()->create(['name' => 'Carlos Mendez']);
    Workflow::factory()->create([
        'type' => WorkflowType::ChangePersonalInfo->value,
        'status' => WorkflowStatus::InProgress,
        'subject_type' => $carlos->getMorphClass(),
        'subject_id' => $carlos->id,
        'data' => ['changes' => ['phone' => '555-0100']],
    ]);

    $allotment = PtoYearAllotment::factory()->create();
    PtoRequest::factory()->create(['year_allotment_id' => $allotment->id, 'person_id' => $allotment->person_id, 'bucket' => PtoBucket::Vacation, 'hours' => 8]);

    $rows = collect(hr()['waiting']['rows'])->keyBy('type');

    expect($rows['Info change']['title'])->toBe('Carlos Mendez · new phone number')
        ->and($rows['Time off']['detail'])->toStartWith('8 h vacation · ')
        ->and($rows['Time off']['detail'])->toEndWith(' h left after this');
});

it('says when a recruiter on time off leaves properties without cover', function () {
    $ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter']);
    foreach (Property::factory()->count(2)->create() as $property) {
        $property->assignments()->create(['person_id' => $ray->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
    }
    PtoRequest::factory()->create(['person_id' => $ray->id, 'status' => PtoRequestStatus::Approved, 'start_date' => '2026-10-15', 'end_date' => '2026-10-15']);

    expect(hr()['staffAway']['rows'][0])->toMatchArray(['name' => 'Ray Recruiter', 'covers' => 2, 'now' => false]);
});

it('lists staff whose hire anniversary moves them up a time-off tier soon', function () {
    Person::factory()->create(['name' => 'Ray', 'status' => PersonStatus::StaffActive, 'hire_date' => '2025-10-28']); // 1 year on Oct 28
    Person::factory()->create(['name' => 'Sam', 'status' => PersonStatus::StaffActive, 'hire_date' => '2026-05-04']);  // 6 months on Nov 4
    Person::factory()->create(['name' => 'Old', 'status' => PersonStatus::StaffActive, 'hire_date' => '2020-01-01']);  // already at the top
    Person::factory()->create(['name' => 'Far', 'status' => PersonStatus::StaffActive, 'hire_date' => '2026-02-01']);  // 6 months was in August

    $tiers = hr()['tiers'];

    expect(collect($tiers)->pluck('name')->all())->toBe(['Ray', 'Sam'])
        ->and($tiers[0])->toMatchArray(['days' => 16, 'detail' => '1 year on Oct 28 · 40 h per bucket, the top tier'])
        ->and($tiers[1]['detail'])->toBe('6 months on Nov 4 · 30 h per bucket');
});
