<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Actions\SubmitApplication;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Notifications\NotificationLink;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('alerts every recruiter in the app when someone applies on the website', function () {
    $recruiters = [person('recruiter'), person('recruiter')];
    $officeManager = person('office_manager');
    $posting = JobPosting::factory()->published()->create(['title' => 'Night Auditor']);

    $this->post(main('/application'), applicationPayload(['job_id' => $posting->id, 'position' => 'Night Auditor']))
        ->assertSessionHasNoErrors();

    $application = JobApplication::query()->firstOrFail();

    foreach ($recruiters as $recruiter) {
        $data = $recruiter->notifications()->sole()->data;
        expect($data['type'])->toBe('application_received')
            ->and($data['category'])->toBe('applications')
            ->and($data['application_id'])->toBe($application->id)
            ->and($data['message'])->toBe('New application from Maria Lopez for Night Auditor.');
    }
    expect($officeManager->notifications()->count())->toBe(0);
});

it('alerts from the Spanish form too', function () {
    $recruiter = person('recruiter');

    $this->post(main('/es/solicitud'), applicationPayload())->assertSessionHasNoErrors();

    expect($recruiter->notifications()->count())->toBe(1);
});

it('flags an application whose email matches someone already on file', function () {
    $recruiter = person('recruiter');
    Person::factory()->create(['email' => 'maria@example.com', 'status' => PersonStatus::StaffActive]);

    $this->post(main('/application'), applicationPayload())->assertSessionHasNoErrors();

    expect($recruiter->notifications()->sole()->data['message'])
        ->toContain('Their email matches an existing person — link or dismiss before reviewing.');
});

it('skips recruiters who muted new applications', function () {
    $muted = person('recruiter');
    $muted->update(['muted_notifications' => ['applications']]);
    $listening = person('recruiter');

    $this->post(main('/application'), applicationPayload())->assertSessionHasNoErrors();

    expect($muted->notifications()->count())->toBe(0)
        ->and($listening->notifications()->count())->toBe(1);
});

it('does not alert anyone for applications recorded outside the website form', function () {
    $recruiter = person('recruiter');

    app(SubmitApplication::class)->handle(applicationPayload());

    expect($recruiter->notifications()->count())->toBe(0);
});

it('opens the application from the bell in the back office', function () {
    expect(NotificationLink::resolve(['type' => 'application_received', 'application_id' => 42], onMinute: false))
        ->toBe('/admin/applicants/42')
        ->and(NotificationLink::resolve(['type' => 'application_received', 'application_id' => 42], onMinute: true))
        ->toBeNull();
});
