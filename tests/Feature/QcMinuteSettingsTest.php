<?php

use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('serves the settings page to a property manager on QC Minute', function () {
    // The audience the timesheet emails are addressed to could not reach any
    // preferences page at all — every settings route was back-office only.
    $this->actingAs(person('property_manager'))->get(qcminute('/settings/profile'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/profile')
            ->where('surface', 'qcminute')
            ->has('notificationSettings.categories'),
        );
});

it('serves it to a contractor too', function () {
    $this->actingAs(person('contractor'))->get(qcminute('/settings/profile'))->assertOk();
});

it('redirects bare /settings to the profile tab', function () {
    $this->actingAs(person('property_manager'))->get(qcminute('/settings'))
        ->assertRedirect(qcminute('/settings/profile'));
});

it('saves notification mutes from QC Minute', function () {
    $pm = person('property_manager');

    $this->actingAs($pm)
        ->patch(qcminute('/settings/notifications'), ['muted' => ['timesheets']])
        ->assertSessionHasNoErrors();

    expect($pm->fresh()->muted_notifications)->toBe(['timesheets']);
});

it('returns to QC Minute after saving, not the back office', function () {
    $pm = person('property_manager');

    // back() rather than the named back-office route — the same controller
    // serves both surfaces.
    $this->actingAs($pm)
        ->from(qcminute('/settings/profile'))
        ->patch(qcminute('/settings/profile'), ['name' => 'Renamed PM', 'email' => $pm->email, 'phone' => null])
        ->assertRedirect(qcminute('/settings/profile'));

    expect($pm->fresh()->name)->toBe('Renamed PM');
});

it('changes a password from QC Minute', function () {
    $pm = person('property_manager');

    $this->actingAs($pm)
        ->from(qcminute('/settings/profile'))
        ->put(qcminute('/settings/password'), [
            'current_password' => 'secret',
            'password' => 'a-much-longer-password',
            'password_confirmation' => 'a-much-longer-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(qcminute('/settings/profile'));
});

it('still works on the back office', function () {
    $manager = person('office_manager');

    $this->actingAs($manager)->get(main('/admin/settings/profile'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('surface', 'backoffice'));

    $this->actingAs($manager)
        ->from(main('/admin/settings/profile'))
        ->patch(main('/admin/settings/notifications'), ['muted' => ['workflows']])
        ->assertRedirect(main('/admin/settings/profile'));
});

it('keeps settings behind auth on QC Minute', function () {
    $this->get(qcminute('/settings/profile'))->assertRedirect();
});
