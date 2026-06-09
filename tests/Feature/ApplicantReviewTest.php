<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function applicant(): Person
{
    return Person::factory()->create(['status' => PersonStatus::Applicant, 'application_date' => now()->toDateString()]);
}

it('shows the pending queue by default and filters by status', function () {
    $hr = person('hr');
    JobApplication::factory()->create(['person_id' => applicant()->id]);
    JobApplication::factory()->reviewing()->create(['person_id' => applicant()->id]);
    JobApplication::factory()->create(['person_id' => applicant()->id, 'status' => JobApplicationStatus::Rejected]);

    $this->actingAs($hr)->get(main('/admin/applicants'))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/applicants/index')
            ->has('applications', 2)
            ->where('counts.pending', 2)
            ->where('counts.rejected', 1));

    $this->actingAs($hr)->get(main('/admin/applicants?status=rejected'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('applications', 1));
});

it('moves a submitted application to reviewing', function () {
    $recruiter = person('recruiter');
    $application = JobApplication::factory()->create(['person_id' => applicant()->id]);

    $this->actingAs($recruiter)->post(main("/admin/applicants/{$application->id}/start-review"))->assertRedirect();

    $application->refresh();
    expect($application->status)->toBe(JobApplicationStatus::Reviewing)
        ->and($application->reviewed_by)->toBe($recruiter->id)
        ->and($application->reviewed_at)->not->toBeNull();
});

it('rejects a pending application with a reason', function () {
    $hr = person('hr');
    $application = JobApplication::factory()->reviewing()->create(['person_id' => applicant()->id]);

    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/reject"), [
        'reason' => 'No weekend availability',
    ])->assertRedirect();

    $application->refresh();
    expect($application->status)->toBe(JobApplicationStatus::Rejected)
        ->and($application->rejected_reason)->toBe('No weekend availability');
});

it('shows the application detail with checklist and permission flags', function () {
    $frontDesk = person('front_desk');
    $application = JobApplication::factory()->create(['person_id' => applicant()->id]);

    $this->actingAs($frontDesk)->get(main("/admin/applicants/{$application->id}"))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/applicants/show')
            ->has('checklist', 6)
            ->where('checklist_complete', false)
            ->where('can.review', false)        // front desk: checklist only (ADR-0013)
            ->where('can.edit_checklist', true)
            ->where('can.promote', false));
});

it('blocks contractors and PMs from the queue', function () {
    $pm = person('property_manager');

    $this->actingAs($pm)->get(main('/admin/applicants'))->assertForbidden();
});
