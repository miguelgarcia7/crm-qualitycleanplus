<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;

it('renders the blank application form', function () {
    $this->get(main('/application'))->assertOk();
});

it('renders the application form pre-filled for a posting', function () {
    $posting = JobPosting::factory()->published()->create(['title' => 'Banquet Server']);

    $this->get(main('/application/'.$posting->slug))->assertOk()->assertSee('Banquet Server');
});

it('hides draft and closed postings from the application form', function (JobPostingStatus $status) {
    $posting = JobPosting::factory()->create(['status' => $status]);

    $this->get(main('/application/'.$posting->slug))->assertNotFound();
})->with([JobPostingStatus::Draft, JobPostingStatus::Closed]);

it('does not link an application to a posting that is not published', function () {
    $posting = JobPosting::factory()->closed()->create();

    $this->post(main('/application'), applicationPayload(['job_id' => $posting->id]))
        ->assertRedirect(route('marketing.application.thank-you'));

    expect(JobApplication::query()->firstOrFail()->job_posting_id)->toBeNull();
});

it('does not capture thank-you as a posting slug', function () {
    $this->get(main('/application/thank-you'))->assertOk()->assertSee('Thank You', false);
});

it('creates an applicant person and job application on submit', function () {
    $this->post(main('/application'), applicationPayload())
        ->assertRedirect(route('marketing.application.thank-you'));

    $person = Person::query()->where('email', 'maria@example.com')->firstOrFail();
    expect($person->status)->toBe(PersonStatus::Applicant)
        ->and($person->name)->toBe('Maria Lopez')
        ->and($person->city)->toBe('Dallas')
        ->and($person->emergency_contact_name)->toBe('Jose Lopez')
        ->and($person->usa_citizen)->toBeTrue()
        ->and($person->application_date)->not->toBeNull();

    $application = JobApplication::query()->where('person_id', $person->id)->firstOrFail();
    expect($application->status)->toBe(JobApplicationStatus::Submitted)
        ->and($application->transportation)->toBeTrue()
        ->and($application->convicted_felon)->toBeFalse()
        ->and($application->desired_position)->toBe('Housekeeper');
});

it('links the application to a posting via job_id', function () {
    $posting = JobPosting::factory()->published()->create();

    $this->post(main('/application'), applicationPayload(['job_id' => $posting->id]))->assertRedirect();

    expect(JobApplication::query()->firstOrFail()->job_posting_id)->toBe($posting->id);
});

it('generates a placeholder email when none is provided', function () {
    $this->post(main('/application'), applicationPayload(['email' => null]))->assertRedirect();

    expect(JobApplication::query()->firstOrFail()->person->email)->toContain('@qcp.invalid');
});

it('links a repeat applicant to the existing person', function () {
    $this->post(main('/application'), applicationPayload())->assertRedirect();
    $this->post(main('/application'), applicationPayload(['position' => 'Houseman']))->assertRedirect();

    expect(Person::query()->where('email', 'maria@example.com')->count())->toBe(1)
        ->and(JobApplication::query()->count())->toBe(2);
});

it('flags, rather than restores, a soft-deleted person who re-applies with the same email', function () {
    $person = Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::Applicant]);
    $person->delete();

    // Email stays unique across soft-deleted rows (one identity per human), so the
    // intake must not collide with it — nor restore it on a stranger's say-so. It
    // waits for a recruiter (ApplicationEmailMatchTest).
    $this->post(main('/application'), applicationPayload())->assertRedirect();

    $application = JobApplication::query()->firstOrFail();
    expect($person->fresh()?->trashed() ?? Person::withTrashed()->find($person->id)->trashed())->toBeTrue()
        ->and(Person::withTrashed()->where('email', 'maria@example.com')->count())->toBe(1)
        ->and($application->matched_person_id)->toBe($person->id);
});

it('rejects an application missing required fields', function () {
    $this->post(main('/application'), ['first_name' => 'X'])
        ->assertSessionHasErrors(['last_name', 'phone', 'address', 'dob', 'transportation', 'acknowledgement']);

    expect(JobApplication::query()->count())->toBe(0);
});

it('requires the acknowledgement to be accepted', function () {
    $this->post(main('/application'), applicationPayload(['acknowledgement' => '0']))
        ->assertSessionHasErrors(['acknowledgement']);
});
