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

it('records a login in the activity log', function () {
    $admin = person('super_admin');

    $this->post(main('/login'), [
        'email' => $admin->email,
        'password' => 'secret',
    ])->assertRedirect('/admin');

    expect(Activity::where('event', 'login')->count())->toBe(1);
});
