<?php

use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows the audit log to authorized roles', function (string $role) {
    activity('testing')->event('created')->log('Something happened');

    $this->actingAs(person($role))
        ->get(main('/admin/audit'))
        ->assertOk()
        ->assertSee('Something happened');
})->with(['admin', 'office_manager']);

it('blocks roles without the audit permission', function (string $role) {
    $this->actingAs(person($role))
        ->get(main('/admin/audit'))
        ->assertForbidden();
})->with(['hr', 'payroll', 'recruiter', 'front_desk']);

it('records and shows logins', function () {
    $user = person('admin');

    $this->post(main('/login'), ['email' => $user->email, 'password' => 'secret']);

    $this->actingAs($user)
        ->get(main('/admin/audit?log=auth'))
        ->assertOk()
        ->assertSee('Logged in');
});

it('filters by search and subject type', function () {
    $property = Property::factory()->create(['name' => 'Audit Hotel']);
    activity('testing')->performedOn($property)->log('Property entry');
    activity('testing')->log('Unrelated entry');

    $this->actingAs(person('admin'))
        ->get(main('/admin/audit?subject='.urlencode($property->getMorphClass())))
        ->assertOk()
        ->assertDontSee('Unrelated entry');

    $this->actingAs(person('admin'))
        ->get(main('/admin/audit?q=Unrelated'))
        ->assertOk()
        ->assertSee('Unrelated entry');
});

it('paginates server-side', function () {
    activity('testing')->log('Oldest entry alpha');
    foreach (range(1, 25) as $i) {
        activity('testing')->log("Filler entry {$i}");
    }

    // 26 entries, newest first, 25 per page: the oldest one falls to page 2.
    $this->actingAs(person('admin'))
        ->get(main('/admin/audit'))
        ->assertOk()
        ->assertDontSee('Oldest entry alpha');

    $this->actingAs(person('admin'))
        ->get(main('/admin/audit?page=2'))
        ->assertOk()
        ->assertSee('Oldest entry alpha');
});
