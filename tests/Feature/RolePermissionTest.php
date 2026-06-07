<?php

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('seeds all ten roles', function () {
    expect(Role::count())->toBe(10);
});

it('seeds the full permission catalog', function () {
    expect(Permission::count())->toBeGreaterThanOrEqual(100);
});

it('grants super_admin every permission', function () {
    $superAdmin = Role::findByName('super_admin');

    expect($superAdmin->permissions()->count())->toBe(Permission::count());
});

it('scopes recruiter permissions correctly', function () {
    $recruiter = Role::findByName('recruiter');

    expect($recruiter->hasPermissionTo('timesheets.submit_for_approval'))->toBeTrue()
        ->and($recruiter->hasPermissionTo('timesheets.approve'))->toBeFalse();
});

it('gives contractor a minimal permission set', function () {
    $contractor = Role::findByName('contractor');

    expect($contractor->hasPermissionTo('people.own_profile.view'))->toBeTrue()
        ->and($contractor->hasPermissionTo('invoices.view'))->toBeFalse();
});
