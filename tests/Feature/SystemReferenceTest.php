<?php

use App\Domain\SystemReference\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows the system reference to Super Admin and Admin', function (string $role) {
    $this->actingAs(person($role))
        ->get(main('/admin/system'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/index')
            ->where('stats.permissions', Permission::query()->count())
            ->where('stats.roles', 10)
            // Six nightly tasks, plus the demo clock when DEMO_MODE is on.
            ->has('automations', count(app(Schedule::class)->events()))
            ->where('stats.automations', count(app(Schedule::class)->events()))
        );
})->with(['super_admin', 'admin']);

it('keeps everyone else out', function (string $role) {
    $this->actingAs(person($role))
        ->get(main('/admin/system'))
        ->assertForbidden();
})->with(['office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee']);

it('lists permissions in plain words with the roles that hold them', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions', fn ($rows) => collect($rows)->contains(fn (array $p): bool => $p['key'] === 'workflows.pto.approve'
                && $p['label'] === 'Approve PTO'
                && $p['area'] === 'Workflows & time off'
                && $p['roles'] === ['Admin', 'HR']))
            // Super Admin-only permissions list no other role.
            ->where('permissions', fn ($rows) => collect($rows)->contains(fn (array $p): bool => $p['key'] === 'devices.manage' && $p['roles'] === []))
        );
});

it('gives every permission a readable label and a known area', function () {
    $catalog = new PermissionCatalog;

    foreach ($catalog->all() as $permission) {
        expect($permission['label'])->not->toContain('_')->not->toContain('.')
            ->and($permission['area'])->not->toBe('Other');
    }
});

it('lists the scheduled tasks in Chicago time', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('automations.0.name', 'Alert Admin and Payroll about contracts ending in 30 or 14 days')
            ->where('automations.0.cadence', fn (string $cadence): bool => in_array($cadence, ['Daily at 1:00 AM', 'Daily at 2:00 AM'], true))
            ->where('automations.1.name', 'Create the upcoming pay weeks')
        );
});

it('describes every scheduled task, so the reference never shows a raw command', function () {
    app(Kernel::class)->bootstrap();

    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();
    foreach ($events as $event) {
        /** @var Event $event */
        expect($event->description)->toBeString()->not->toBeEmpty();
    }
});

it('shows every permission against every role, grouped by area', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system/roles'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/roles')
            ->has('roles', 9) // Super Admin holds everything, so it isn't a column
            ->where('roles.0', ['key' => 'admin', 'label' => 'Admin', 'total' => Permission::role('admin')->count()])
            ->where('total', Permission::query()->count())
            ->where('groups.0.name', 'Property Bible')
            ->where('groups', fn ($groups) => collect($groups)->flatMap(fn (array $g) => $g['permissions'])->contains(
                fn (array $p): bool => $p['key'] === 'timesheets.approve' && $p['roles'] === ['admin', 'property_manager'] && ! $p['super_only']
            ))
            ->where('groups', fn ($groups) => collect($groups)->flatMap(fn (array $g) => $g['permissions'])->contains(
                fn (array $p): bool => $p['key'] === 'devices.manage' && $p['super_only'] && $p['roles'] === []
            ))
            ->where('groups', fn ($groups) => collect($groups)->flatMap(fn (array $g) => $g['permissions'])->contains(
                fn (array $p): bool => $p['key'] === 'field_visits.view_own' && $p['scope'] === 'Own records only'
            ))
        );
});

it('opens the roles page on a highlighted role and a search from the link', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system/roles?role=recruiter&q=invoices.send'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('initial.role', 'recruiter')
            ->where('initial.q', 'invoices.send')
        );

    // An unknown role (or Super Admin, which has no column) highlights nothing.
    $this->actingAs(person('admin'))
        ->get(main('/admin/system/roles?role=super_admin'))
        ->assertInertia(fn (Assert $page) => $page->where('initial.role', null));
});

it('keeps everyone else out of the roles page', function (string $role) {
    $this->actingAs(person($role))
        ->get(main('/admin/system/roles'))
        ->assertForbidden();
})->with(['office_manager', 'hr', 'payroll', 'recruiter']);
