<?php

use App\Domain\People\Enums\BackgroundCheckStatus;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake(config('filesystems.default'));
});

function reviewingApplication(): JobApplication
{
    $person = Person::factory()->create(['status' => PersonStatus::Applicant, 'application_date' => now()->toDateString()]);

    return JobApplication::factory()->reviewing()->create(['person_id' => $person->id]);
}

/** Complete every checklist item for the application's person (HR waives nothing). */
function completeChecklist(JobApplication $application, Person $actor): void
{
    $person = $application->person;
    foreach (array_keys(OnboardingChecklist::DOCUMENTS) as $item) {
        test()->actingAs($actor)->post(main("/admin/applicants/{$application->id}/onboarding/{$item}"), [
            'document' => UploadedFile::fake()->create("{$item}.pdf", 100, 'application/pdf'),
        ])->assertRedirect();
    }
    test()->actingAs($actor)->post(main("/admin/applicants/{$application->id}/onboarding/i9/verify"))->assertRedirect();
    test()->actingAs($actor)->post(main("/admin/applicants/{$application->id}/background-check"), ['status' => 'passed'])->assertRedirect();
}

it('uploads a checklist document and stamps the person columns', function () {
    $frontDesk = person('front_desk');
    $application = reviewingApplication();

    $this->actingAs($frontDesk)->post(main("/admin/applicants/{$application->id}/onboarding/id_front"), [
        'document' => UploadedFile::fake()->create('id-front.jpg', 200, 'image/jpeg'),
    ])->assertRedirect();

    $person = $application->person->fresh();
    expect($person->id_front_file_id)->not->toBeNull()
        ->and($person->id_front_uploaded_at)->not->toBeNull();

    $this->actingAs($frontDesk)
        ->get(main("/admin/applicants/{$application->id}/onboarding/id_front/download"))
        ->assertOk();
});

it('re-uploading the I-9 clears prior verification', function () {
    $hr = person('hr');
    $application = reviewingApplication();

    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/onboarding/i9"), [
        'document' => UploadedFile::fake()->create('i9.pdf', 100, 'application/pdf'),
    ]);
    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/onboarding/i9/verify"));
    expect($application->person->fresh()->i9_verified_at)->not->toBeNull();

    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/onboarding/i9"), [
        'document' => UploadedFile::fake()->create('i9-v2.pdf', 100, 'application/pdf'),
    ]);
    expect($application->person->fresh()->i9_verified_at)->toBeNull();
});

it('lets HR waive an item and blocks front desk from waiving', function () {
    $hr = person('hr');
    $frontDesk = person('front_desk');
    $application = reviewingApplication();

    $this->actingAs($frontDesk)->post(main("/admin/applicants/{$application->id}/onboarding/w9/waive"), ['waived' => true])
        ->assertForbidden();

    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/onboarding/w9/waive"), ['waived' => true])
        ->assertRedirect();
    expect(OnboardingChecklist::waivedItems($application->person->fresh()))->toBe(['w9']);

    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/onboarding/w9/waive"), ['waived' => false]);
    expect(OnboardingChecklist::waivedItems($application->person->fresh()))->toBe([]);
});

it('blocks promotion while the checklist is incomplete', function () {
    $hr = person('hr');
    $application = reviewingApplication();

    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/promote"))
        ->assertSessionHasErrors(['checklist']);

    expect($application->person->fresh()->status)->toBe(PersonStatus::Applicant);
});

it('promotes once the checklist is complete and sets the lifecycle fields', function () {
    $recruiter = person('recruiter');
    $application = reviewingApplication();
    completeChecklist($application, person('hr'));

    $this->actingAs($recruiter)->post(main("/admin/applicants/{$application->id}/promote"))->assertRedirect();

    $person = $application->person->fresh();
    $application->refresh();
    expect($person->status)->toBe(PersonStatus::ContractorActive)
        ->and($person->converted_to_contractor_at)->not->toBeNull()
        ->and($person->primary_recruiter_id)->toBe($recruiter->id)
        ->and($person->hasRole('contractor'))->toBeTrue()
        ->and($application->status)->toBe(JobApplicationStatus::Promoted)
        ->and($application->promoted_by)->toBe($recruiter->id);
});

it('lets the promoter reverse while no work orders exist', function () {
    $recruiter = person('recruiter');
    $application = reviewingApplication();
    completeChecklist($application, person('hr'));
    $this->actingAs($recruiter)->post(main("/admin/applicants/{$application->id}/promote"));

    $this->actingAs($recruiter)->post(main("/admin/applicants/{$application->id}/reverse"))->assertRedirect();

    $person = $application->person->fresh();
    $application->refresh();
    expect($person->status)->toBe(PersonStatus::Applicant)
        ->and($person->converted_to_contractor_at)->toBeNull()
        ->and($person->hasRole('contractor'))->toBeFalse()
        ->and($application->status)->toBe(JobApplicationStatus::Reviewing);
});

it('blocks reversal once a work order exists', function () {
    $recruiter = person('recruiter');
    $application = reviewingApplication();
    completeChecklist($application, person('hr'));
    $this->actingAs($recruiter)->post(main("/admin/applicants/{$application->id}/promote"));

    WorkOrder::factory()->create(['person_id' => $application->person_id]);

    $this->actingAs($recruiter)->post(main("/admin/applicants/{$application->id}/reverse"))
        ->assertSessionHasErrors(['status']);
    expect($application->person->fresh()->status)->toBe(PersonStatus::ContractorActive);
});

it('blocks a different recruiter from reversing someone else\'s promotion', function () {
    $promoter = person('recruiter');
    $other = person('recruiter');
    $application = reviewingApplication();
    completeChecklist($application, person('hr'));
    $this->actingAs($promoter)->post(main("/admin/applicants/{$application->id}/promote"));

    $this->actingAs($other)->post(main("/admin/applicants/{$application->id}/reverse"))->assertForbidden();
});

it('treats a failed background check as blocking', function () {
    $hr = person('hr');
    $application = reviewingApplication();
    completeChecklist($application, $hr);

    $this->actingAs($hr)->post(main("/admin/applicants/{$application->id}/background-check"), ['status' => 'failed']);

    expect($application->person->fresh()->background_check_status)->toBe(BackgroundCheckStatus::Failed)
        ->and(OnboardingChecklist::missingItems($application->person->fresh()))->toBe(['background_check']);
});
