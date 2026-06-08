<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Notifications\WorkflowNotice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
});

function selfRequest(Person $person, array $changes): Workflow
{
    test()->actingAs($person)->post(qcminute('/my-info'), $changes + ['reason' => 'Updating my details'])->assertRedirect();

    return Workflow::query()
        ->where('type', WorkflowType::ChangePersonalInfo->value)
        ->where('initiator_id', $person->id)
        ->latest('id')
        ->firstOrFail();
}

it('lets an employee self-request a change pending HR verification', function () {
    $contractor = person('contractor');
    $workflow = selfRequest($contractor, ['phone' => '(555) 123-4567']);

    expect($workflow->status)->toBe(WorkflowStatus::InProgress)
        ->and($workflow->currentStep()->step_key)->toBe('verify_change')
        ->and($workflow->data['changes'])->toBe(['phone' => '(555) 123-4567']);
});

it('applies the change and normalizes phone when HR verifies', function () {
    $contractor = person('contractor');
    $workflow = selfRequest($contractor, ['phone' => '(555) 123-4567']);

    $this->actingAs(person('hr'))
        ->post(main("/admin/info-changes/{$workflow->id}/approve"))
        ->assertRedirect();

    expect($contractor->fresh()->phone)->toBe('(555) 123-4567')
        ->and($contractor->fresh()->normalized_phone)->toBe('5551234567')
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Completed);

    Notification::assertSentTo($contractor, WorkflowNotice::class);
});

it('nulls email verification when the email changes', function () {
    $contractor = person('contractor');
    $contractor->update(['email_verified_at' => now()]);
    $workflow = selfRequest($contractor, ['email' => 'new-address@example.com']);

    $this->actingAs(person('hr'))->post(main("/admin/info-changes/{$workflow->id}/approve"));

    expect($contractor->fresh()->email)->toBe('new-address@example.com')
        ->and($contractor->fresh()->email_verified_at)->toBeNull();
});

it('leaves the person unchanged when HR declines', function () {
    $contractor = person('contractor');
    $originalPhone = $contractor->phone;
    $workflow = selfRequest($contractor, ['phone' => '(555) 999-0000']);

    $this->actingAs(person('hr'))
        ->post(main("/admin/info-changes/{$workflow->id}/decline"), ['reason' => 'Could not verify identity'])
        ->assertRedirect();

    expect($contractor->fresh()->phone)->toBe($originalPhone)
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Rejected);

    Notification::assertSentTo($contractor, WorkflowNotice::class);
});

it('lets HR initiate a change on behalf of someone', function () {
    $staff = Person::factory()->create(['status' => PersonStatus::StaffActive, 'name' => 'Old Name']);

    $this->actingAs(person('hr'))->post(main('/admin/info-changes'), [
        'person_id' => $staff->id, 'name' => 'New Name', 'reason' => 'Legal name change',
    ])->assertRedirect();

    $workflow = Workflow::query()->where('type', WorkflowType::ChangePersonalInfo->value)->latest('id')->firstOrFail();
    expect($workflow->data['changes'])->toBe(['name' => 'New Name']);

    $this->actingAs(person('hr'))->post(main("/admin/info-changes/{$workflow->id}/approve"));
    expect($staff->fresh()->name)->toBe('New Name');
});

it('rejects a request with no changed fields', function () {
    $contractor = person('contractor');

    $this->actingAs($contractor)->post(qcminute('/my-info'), [
        'name' => $contractor->name, 'reason' => 'no real change',
    ])->assertSessionHasErrors('name');
});

it('forbids a non-HR user from verifying a change', function () {
    $contractor = person('contractor');
    $workflow = selfRequest($contractor, ['phone' => '(555) 123-4567']);

    $this->actingAs(person('recruiter'))
        ->post(main("/admin/info-changes/{$workflow->id}/approve"))
        ->assertForbidden();
});
