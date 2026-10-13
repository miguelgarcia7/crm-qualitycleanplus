<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->hotel = Property::factory()->create(['name' => 'Acme Hotel']);
    $this->rita = tap(person('recruiter'))->update(['name' => 'Rita Recruiter', 'status' => PersonStatus::StaffActive]);
    $this->ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter', 'status' => PersonStatus::StaffActive]);
    $this->hotel->assignments()->create(['person_id' => $this->rita->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
});

function contractorOf(Person $recruiter, string $name): Person
{
    return Person::factory()->create(['name' => $name, 'status' => PersonStatus::ContractorActive, 'primary_recruiter_id' => $recruiter->id])->assignRole('contractor');
}

// --- Roles -------------------------------------------------------------------------

it('lets HR give a staff member a second role, and logs it on their History', function () {
    $hr = person('hr');

    $this->actingAs($hr)
        ->put(main("/admin/people/{$this->ray->id}/roles"), ['roles' => ['recruiter', 'hr']])
        ->assertSessionHasNoErrors();

    expect($this->ray->fresh()->getRoleNames()->sort()->values()->all())->toBe(['hr', 'recruiter'])
        ->and(Activity::query()->where('subject_id', $this->ray->id)->where('event', 'roles_changed')->value('description'))
        ->toBe('Roles changed from Recruiter to HR, Recruiter');
});

it('never changes your own roles, an admin’s, or a property manager’s', function () {
    $hr = tap(person('hr'))->update(['status' => PersonStatus::StaffActive]);

    $this->actingAs($hr)->put(main("/admin/people/{$hr->id}/roles"), ['roles' => ['hr', 'payroll']])->assertForbidden();
    $this->actingAs($hr)->put(main('/admin/people/'.person('admin')->id.'/roles'), ['roles' => ['hr']])->assertForbidden();
    $this->actingAs($hr)->put(main('/admin/people/'.person('property_manager')->id.'/roles'), ['roles' => ['hr']])->assertForbidden();

    // And nobody gets admin or property manager this way.
    $this->actingAs($hr)->put(main("/admin/people/{$this->ray->id}/roles"), ['roles' => ['admin']])->assertSessionHasErrors('roles.0');
});

it('keeps role changes to whoever can invite people', function () {
    $this->actingAs(person('payroll'))->put(main("/admin/people/{$this->ray->id}/roles"), ['roles' => ['payroll']])->assertForbidden();
});

it('won’t take Recruiter away while they still have contractors', function () {
    contractorOf($this->rita, 'James Carter');

    $this->actingAs(person('admin'))
        ->put(main("/admin/people/{$this->rita->id}/roles"), ['roles' => ['hr']])
        ->assertSessionHasErrors(['roles' => 'Rita Recruiter still has 1 contractor and 1 property. Move them to another recruiter first.']);

    expect($this->rita->fresh()->hasRole('recruiter'))->toBeTrue();
});

// --- Moving contractors ------------------------------------------------------------------

it('moves a recruiter’s contractors, open hours problems and hotel to another recruiter', function () {
    $james = contractorOf($this->rita, 'James Carter');
    $maria = contractorOf($this->rita, 'Maria Lopez');
    $stays = contractorOf($this->rita, 'Aisha Brown');
    $workflow = app(StartWorkflow::class)->handle(WorkflowType::HoursProblem, $james, $james, ['week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'message' => 'Tuesday is off']);

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/people/{$this->rita->id}/contractors/move"), [
            'to' => $this->ray->id,
            'contractor_ids' => [$james->id, $maria->id],
            'give_properties' => true,
            'remove_properties' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($james->fresh()->primary_recruiter_id)->toBe($this->ray->id)
        ->and($maria->fresh()->primary_recruiter_id)->toBe($this->ray->id)
        ->and($stays->fresh()->primary_recruiter_id)->toBe($this->rita->id)
        ->and(WorkflowStep::query()->where('workflow_id', $workflow->id)->value('assigned_to'))->toBe($this->ray->id)
        ->and($this->hotel->assignments()->where('person_id', $this->ray->id)->exists())->toBeTrue()
        ->and($this->hotel->assignments()->where('person_id', $this->rita->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('subject_id', $james->id)->where('event', 'recruiter_changed')->value('description'))
        ->toBe('Recruiter changed from Rita Recruiter to Ray Recruiter');
});

it('lets a recruiter hand over their own contractors, and only their own', function () {
    $james = contractorOf($this->rita, 'James Carter');
    $rays = contractorOf($this->ray, 'Tom Nguyen');

    $this->actingAs($this->rita)
        ->post(main("/admin/people/{$this->rita->id}/contractors/move"), ['to' => $this->ray->id, 'contractor_ids' => [$james->id]])
        ->assertSessionHasNoErrors();
    expect($james->fresh()->primary_recruiter_id)->toBe($this->ray->id);

    // Ray's contractors aren't Rita's to move.
    $this->actingAs($this->rita)
        ->post(main("/admin/people/{$this->ray->id}/contractors/move"), ['to' => $this->rita->id, 'contractor_ids' => [$rays->id]])
        ->assertForbidden();
    $this->actingAs($this->rita)->put(main("/admin/people/{$rays->id}/recruiter"), ['to' => $this->rita->id])->assertForbidden();

    // Someone else's contractor slipped into the list is refused.
    $this->actingAs(person('admin'))
        ->post(main("/admin/people/{$this->rita->id}/contractors/move"), ['to' => $this->ray->id, 'contractor_ids' => [$rays->id]])
        ->assertSessionHasErrors('contractor_ids.0');
});

it('changes one contractor’s recruiter from their page', function () {
    $james = contractorOf($this->rita, 'James Carter');

    $this->actingAs(person('hr'))
        ->put(main("/admin/people/{$james->id}/recruiter"), ['to' => $this->ray->id])
        ->assertSessionHasNoErrors();

    expect($james->fresh()->primary_recruiter_id)->toBe($this->ray->id);

    $this->actingAs(person('front_desk'))->put(main("/admin/people/{$james->id}/recruiter"), ['to' => $this->rita->id])->assertForbidden();
});

it('shows a recruiter’s page who they look after, and who can take them', function () {
    contractorOf($this->rita, 'James Carter');

    $this->actingAs(person('admin'))
        ->get(main("/admin/people/{$this->rita->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('access.roles', ['recruiter'])
            ->where('access.has_password', true)
            ->where('access.properties', ['Acme Hotel'])
            ->where('access.can_change_roles', true)
            ->has('book.contractors', 1)
            ->where('book.properties', ['Acme Hotel'])
            ->where('book.can_move', true)
            ->where('book.recruiters', [['id' => $this->ray->id, 'name' => 'Ray Recruiter']]));
});

// --- Invitations ---------------------------------------------------------------------

it('gives an invitation 3 days, while a forgotten-password link keeps 60 minutes', function () {
    $invited = Person::factory()->create(['email' => 'new@qcp.example.com', 'password' => null, 'status' => PersonStatus::StaffActive])->assignRole('hr');
    $token = Password::broker('invites')->createToken($invited);

    $this->travel(2)->days();
    $this->post(main('/reset-password'), ['token' => $token, 'email' => $invited->email, 'password' => 'NewSecret123!', 'password_confirmation' => 'NewSecret123!'])
        ->assertSessionHasNoErrors();
    expect($invited->fresh()->password)->not->toBeNull();

    $forgot = person('hr');
    $resetToken = Password::broker()->createToken($forgot);
    $this->travel(2)->hours();
    $this->post(main('/reset-password'), ['token' => $resetToken, 'email' => $forgot->email, 'password' => 'NewSecret123!', 'password_confirmation' => 'NewSecret123!'])
        ->assertSessionHasErrors('email');
});

it('sends a new invitation to someone who never set a password', function () {
    Notification::fake();
    $invited = Person::factory()->create(['password' => null, 'status' => PersonStatus::StaffActive])->assignRole('front_desk');

    $this->actingAs(person('hr'))->post(main("/admin/people/{$invited->id}/invitation"))->assertSessionHasNoErrors();
    Notification::assertSentTo($invited, UserInvitation::class);

    // Not once they've signed in.
    $this->actingAs(person('hr'))->post(main("/admin/people/{$this->ray->id}/invitation"))->assertStatus(422);
});

// --- Dashboard switch ------------------------------------------------------------------

it('lets someone with two roles switch dashboards, and remembers the pick', function () {
    $both = tap(person('hr'))->assignRole('recruiter');

    $this->actingAs($both)
        ->get(main('/admin/dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard/hr')
            ->where('dashboard_switch.current', 'hr')
            ->where('dashboard_switch.options.1.key', 'recruiter'));

    $this->actingAs($both)->get(main('/admin/dashboard?as=recruiter'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard/recruiter'));

    $this->actingAs($both)->get(main('/admin/dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard/recruiter')->where('dashboard_switch.current', 'recruiter'));

    // One role, no switch.
    $this->actingAs(person('payroll'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard/payroll')->where('dashboard_switch', null));
});
