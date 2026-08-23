<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
});

it('creates a property manager, assigns properties, and emails an invitation for QC Minute', function () {
    $propertyA = Property::factory()->create();
    $propertyB = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/people/invite'), [
            'role' => 'property_manager',
            'name' => 'Paula Manager',
            'email' => 'paula@hotel.example.com',
            'phone' => '(214) 555-1234',
            'property_ids' => [$propertyA->id, $propertyB->id],
        ])
        ->assertRedirect(main('/admin/people'));

    $person = Person::firstWhere('email', 'paula@hotel.example.com');
    expect($person)->not->toBeNull()
        ->and($person->status)->toBe(PersonStatus::StaffActive)
        ->and($person->password)->toBeNull()
        ->and($person->hire_date)->toBeNull()
        ->and($person->normalized_phone)->toBe('2145551234')
        ->and($person->hasRole('property_manager'))->toBeTrue();

    expect($propertyA->assignments()->where('person_id', $person->id)->where('role', PropertyAssignmentRole::PropertyManager->value)->exists())->toBeTrue()
        ->and($propertyB->assignments()->where('person_id', $person->id)->exists())->toBeTrue();

    Notification::assertSentTo($person, UserInvitation::class, function (UserInvitation $notification) use ($person): bool {
        $mail = $notification->toMail($person);

        return str_contains($mail->actionUrl, config('domains.qcminute'))
            && str_contains($mail->actionUrl, '/reset-password/');
    });
});

it('creates a recruiter with a hire date, an optional property book, and a back-office link', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('hr'))
        ->post(main('/admin/people/invite'), [
            'role' => 'recruiter',
            'name' => 'Rita Recruiter',
            'email' => 'rita@qcp.example.com',
            'hire_date' => '2026-08-01',
            'property_ids' => [$property->id],
        ])
        ->assertRedirect();

    $person = Person::firstWhere('email', 'rita@qcp.example.com');
    expect($person->hasRole('recruiter'))->toBeTrue()
        ->and($person->hire_date->toDateString())->toBe('2026-08-01')
        ->and($property->assignments()->where('person_id', $person->id)->where('role', PropertyAssignmentRole::Recruiter->value)->exists())->toBeTrue();

    Notification::assertSentTo($person, UserInvitation::class, function (UserInvitation $notification) use ($person): bool {
        return str_contains($notification->toMail($person)->actionUrl, config('domains.main'));
    });
});

it('creates office staff without properties, defaulting the hire date to today', function () {
    $this->actingAs(person('admin'))
        ->post(main('/admin/people/invite'), [
            'role' => 'front_desk',
            'name' => 'Fran Front Desk',
            'email' => 'fran@qcp.example.com',
        ])
        ->assertRedirect();

    $person = Person::firstWhere('email', 'fran@qcp.example.com');
    expect($person->hasRole('front_desk'))->toBeTrue()
        ->and($person->hire_date->toDateString())->toBe(now()->toDateString())
        ->and($person->assignedProperties()->count())->toBe(0);
});

it('rejects properties on roles that do not take them', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/people/invite'), [
            'role' => 'payroll',
            'name' => 'Pat Payroll',
            'email' => 'pat@qcp.example.com',
            'property_ids' => [$property->id],
        ])
        ->assertSessionHasErrors('property_ids');
});

it('refuses to mint admin-tier roles', function () {
    foreach (['admin', 'super_admin'] as $role) {
        $this->actingAs(person('office_manager'))
            ->post(main('/admin/people/invite'), [
                'role' => $role,
                'name' => 'Evil',
                'email' => "evil-{$role}@example.com",
            ])
            ->assertSessionHasErrors('role');
    }

    expect(Person::where('email', 'like', 'evil-%')->count())->toBe(0);
});

it('requires at least one property for a property manager', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/people/invite'), [
            'role' => 'property_manager',
            'name' => 'No Props',
            'email' => 'noprops@example.com',
            'property_ids' => [],
        ])
        ->assertSessionHasErrors('property_ids');
});

it('rejects a duplicate email, including one on a soft-deleted person', function () {
    $property = Property::factory()->create();
    $existing = Person::factory()->create(['email' => 'taken@example.com']);
    $existing->delete();

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/people/invite'), [
            'role' => 'property_manager',
            'name' => 'Dup',
            'email' => 'taken@example.com',
            'property_ids' => [$property->id],
        ])
        ->assertSessionHasErrors('email');
});

it('forbids recruiters and payroll from inviting', function () {
    $property = Property::factory()->create();

    foreach (['recruiter', 'payroll'] as $role) {
        $this->actingAs(person($role))
            ->post(main('/admin/people/invite'), [
                'role' => 'property_manager',
                'name' => 'X',
                'email' => "x-{$role}@example.com",
                'property_ids' => [$property->id],
            ])
            ->assertForbidden();
    }

    $this->actingAs(person('recruiter'))->get(main('/admin/people/invite'))->assertForbidden();
});

it('lets the invited user set a password through the reset flow and reach their surface', function () {
    Notification::fake();
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/people/invite'), [
            'role' => 'property_manager',
            'name' => 'Login Test',
            'email' => 'login-test@hotel.example.com',
            'property_ids' => [$property->id],
        ])
        ->assertRedirect();

    $person = Person::firstWhere('email', 'login-test@hotel.example.com');
    $token = Password::broker()->createToken($person);

    // The invitee follows the link as a guest, not as the inviting OM.
    auth()->guard('web')->logout();
    $this->flushSession();

    $this->post(qcminute('/reset-password'), [
        'token' => $token,
        'email' => $person->email,
        'password' => 'NewSecret123!',
        'password_confirmation' => 'NewSecret123!',
    ])->assertSessionHasNoErrors();

    expect($person->fresh()->password)->not->toBeNull();

    $this->actingAs($person->fresh())
        ->get(qcminute('/'))
        ->assertOk();
});
