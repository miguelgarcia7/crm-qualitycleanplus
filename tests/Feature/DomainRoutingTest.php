<?php

use Database\Seeders\RolePermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('serves the public marketing page', function () {
    $this->get(main('/'))
        ->assertOk()
        ->assertSee('Quality Cleaning Plus');
});

it('redirects guests away from the back office', function () {
    $this->get(main('/admin/dashboard'))->assertStatus(302);
});

it('lets a super admin into the back office', function () {
    $this->actingAs(person('super_admin'))
        ->get(main('/admin/dashboard'))
        ->assertOk();
});

it('blocks a contractor from the back office (wrong door)', function () {
    $this->actingAs(person('contractor'))
        ->get(main('/admin/dashboard'))
        ->assertForbidden();
});

it('redirects guests away from qc minute', function () {
    $this->get(qcminute('/'))->assertStatus(302);
});

it('lets a property manager into qc minute', function () {
    $this->actingAs(person('property_manager'))
        ->get(qcminute('/'))
        ->assertOk();
});

it('blocks a w2 employee from qc minute (wrong door)', function () {
    $this->actingAs(person('w2_employee'))
        ->get(qcminute('/'))
        ->assertForbidden();
});

it('gives a wrong-door account a way to sign out from the 403 page', function () {
    // The error page shows the reason and, for a signed-in account, a
    // "Sign out and use a different account" button (views/error.tsx).
    $this->actingAs(person('w2_employee'))
        ->get(qcminute('/'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 403)
            ->where('message', 'This account cannot access QC Minute.')
            ->where('signed_in', true));

    $this->post(qcminute('/logout'))->assertRedirect();
    $this->assertGuest();
});

it('records a login in the activity log', function () {
    $admin = person('super_admin');

    $this->post(main('/login'), [
        'email' => $admin->email,
        'password' => 'secret',
    ])->assertRedirect('/admin');

    expect(Activity::where('event', 'login')->count())->toBe(1);
});

it('signs out to the sign-in page of the same site, not the marketing home', function (string $site) {
    $url = $site === 'main' ? main(...) : qcminute(...);
    $this->actingAs(person('admin'))
        ->post($url('/logout'), [], ['X-Inertia' => 'true'])
        ->assertRedirect($url('/login'));
    $this->assertGuest();
})->with(['main', 'qcminute']);
