<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Submit the public form as someone using $email; returns the application. */
function applyAs(string $email): JobApplication
{
    test()->post(main('/application'), applicationPayload(['email' => $email]))->assertRedirect();

    return JobApplication::query()->latest('id')->firstOrFail();
}

// --- Intake ----------------------------------------------------------------------

it('links a live applicant who re-applies, without flagging it', function () {
    $existing = Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::Applicant]);

    $application = applyAs('maria@example.com');

    expect($application->person_id)->toBe($existing->id)
        ->and($application->matched_person_id)->toBeNull()
        ->and($application->submitted_email)->toBe('maria@example.com');
});

it('does not attach an application to staff, contractor or archived records', function (string $kind) {
    $match = match ($kind) {
        'staff' => tap(person('recruiter'))->update(['email' => 'maria@example.com']),
        'contractor' => Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::ContractorInactive]),
        'archived applicant' => tap(Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::Applicant]))->delete(),
    };
    $before = $match->only(['name', 'phone', 'status', 'deleted_at']);

    $application = applyAs('maria@example.com');
    $match = Person::withTrashed()->findOrFail($match->id);

    expect($application->person_id)->not->toBe($match->id)
        ->and($application->matched_person_id)->toBe($match->id)
        ->and($application->person->email)->toEndWith('@qcp.invalid')
        ->and($application->person->status)->toBe(PersonStatus::Applicant)
        // The matched record is untouched — still archived if it was, same details.
        ->and($match->only(['name', 'phone', 'status', 'deleted_at']))->toEqual($before);
})->with(['staff', 'contractor', 'archived applicant']);

it('keeps an archived match archived until a recruiter links it', function () {
    $archived = tap(Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::ContractorInactive]))->delete();

    applyAs('maria@example.com');

    expect(Person::withTrashed()->find($archived->id)->trashed())->toBeTrue();
});

// --- Review screen --------------------------------------------------------------

it('shows the match and the typed email to the recruiter', function () {
    $match = Person::factory()->create(['email' => 'maria@example.com', 'name' => 'Maria Old', 'status' => PersonStatus::ContractorInactive]);
    $application = applyAs('maria@example.com');
    // The marketing POST pointed Vite at the site bundle; in production each
    // request starts fresh, but here the admin page shares the instance.
    $this->withoutVite();

    $this->actingAs(person('recruiter'))->get(main("/admin/applicants/{$application->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('match.id', $match->id)
            ->where('match.name', 'Maria Old')
            ->where('match.status_label', 'Contractor Inactive')
            ->where('match.archived', false)
            ->where('person.email', 'maria@example.com'));

    $this->actingAs(person('recruiter'))->get(main('/admin/applicants'))
        ->assertInertia(fn ($page) => $page->where('applications.0.email_match', true)->where('applications.0.email', 'maria@example.com'));
});

it('will not start review while the match is unresolved', function () {
    Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::ContractorInactive]);
    $application = applyAs('maria@example.com');

    $this->actingAs(person('recruiter'))->post(main("/admin/applicants/{$application->id}/start-review"))->assertStatus(422);
});

// --- Link / dismiss -------------------------------------------------------------

it('links the application to the matched person, restoring them and archiving the placeholder', function () {
    $archived = tap(Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::ContractorInactive]))->delete();
    $application = applyAs('maria@example.com');
    $placeholder = $application->person;

    $this->actingAs(person('recruiter'))->post(main("/admin/applicants/{$application->id}/match/link"))
        ->assertRedirect(route('backoffice.applicants.show', $application));

    $application->refresh();
    expect($application->person_id)->toBe($archived->id)
        ->and($application->matched_person_id)->toBeNull()
        ->and(Person::query()->find($archived->id))->not->toBeNull()
        ->and(Person::withTrashed()->find($placeholder->id)->trashed())->toBeTrue();
});

it('keeps a separate applicant when the recruiter says it is a different person', function () {
    $match = Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::ContractorInactive]);
    $application = applyAs('maria@example.com');
    $placeholder = $application->person;

    $this->actingAs(person('recruiter'))->post(main("/admin/applicants/{$application->id}/match/dismiss"))->assertRedirect();

    $application->refresh();
    expect($application->matched_person_id)->toBeNull()
        ->and($application->person_id)->toBe($placeholder->id)
        ->and($match->fresh()->jobApplications()->count())->toBe(0);

    $this->actingAs(person('recruiter'))->post(main("/admin/applicants/{$application->id}/start-review"))->assertRedirect();
    expect($application->fresh()->status)->toBe(JobApplicationStatus::Reviewing);
});

it('refuses to link once the application is no longer just submitted', function () {
    Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::ContractorInactive]);
    $application = applyAs('maria@example.com');
    $application->update(['status' => JobApplicationStatus::Rejected, 'rejected_reason' => 'spam']);

    $this->actingAs(person('recruiter'))->post(main("/admin/applicants/{$application->id}/match/link"))
        ->assertSessionHasErrors('match');
});

it('lets only reviewers resolve a match', function () {
    Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::ContractorInactive]);
    $application = applyAs('maria@example.com');

    $this->actingAs(person('contractor'))->post(main("/admin/applicants/{$application->id}/match/link"))->assertForbidden();
    $this->actingAs(person('contractor'))->post(main("/admin/applicants/{$application->id}/match/dismiss"))->assertForbidden();
});
