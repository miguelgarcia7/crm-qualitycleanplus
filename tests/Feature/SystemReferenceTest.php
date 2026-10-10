<?php

use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\SystemReference\Support\NotificationCatalog;
use App\Domain\SystemReference\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\Notification;
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

it('shows every notification against the roles that receive it', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system/notifications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/notifications')
            ->has('roles', 10) // nine roles plus outside inboxes
            ->has('notices', 21)
            ->where('initial', 'timesheet-submitted')
            ->where('notices.0', fn ($n) => $n['name'] === 'Week waiting for approval'
                && $n['in_app'] && $n['email'] && ! $n['can_mute']
                && $n['audience'] === ['property_manager' => 'always'])
        );
});

it('reads delivery and muting from the notification classes', function () {
    $byId = collect((new NotificationCatalog)->all())->keyBy('id');

    // The outcome notices can be muted under Timesheets; the request itself can't.
    expect($byId['timesheet-approved'])
        ->in_app->toBeTrue()->email->toBeTrue()->can_mute->toBeTrue()->mute_category->toBe('Timesheets')
        ->and($byId['punch-flagged'])->email->toBeFalse()->mute_category->toBe('Clock-in alerts')
        ->and($byId['invoice-sent'])->in_app->toBeFalse()->email->toBeTrue()->can_mute->toBeFalse()
        ->and($byId['invoice-sent']['audience'])->toBe([NotificationCatalog::OUTSIDE => 'always'])
        // Recipients that follow a permission are read from it.
        // Super Admin holds every permission, so it's included (it isn't a column on the page).
        ->and(array_keys($byId['contract-expiring']['audience']))->toEqualCanonicalizing(['super_admin', 'admin', 'payroll'])
        ->and($byId['staffing-declined']['audience'])->toMatchArray(['property_manager' => 'if_theirs', 'recruiter' => 'if_theirs']);
});

it('lists every notification class the app has, so a new one cannot go missing', function () {
    $listed = collect((new NotificationCatalog)->all())->flatMap(fn (array $n) => $n['classes'])->unique();

    $classes = collect(glob(app_path('Notifications/*.php')))
        ->map(fn (string $file): string => 'App\\Notifications\\'.basename($file, '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Notification::class) && ! (new ReflectionClass($class))->isAbstract());

    expect($classes)->not->toBeEmpty();
    foreach ($classes as $class) {
        expect($listed)->toContain(class_basename($class));
    }
});

it('opens a notice from a link, and falls back to the first one', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system/notifications?n=contract-expiring'))
        ->assertInertia(fn (Assert $page) => $page->where('initial', 'contract-expiring'));

    $this->actingAs(person('admin'))
        ->get(main('/admin/system/notifications?n=nope'))
        ->assertInertia(fn (Assert $page) => $page->where('initial', 'timesheet-submitted'));
});

it('counts notifications on the overview', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.notifications', 21)
            ->where('stats.notifications_email', 7) // three timesheet notices, invoice, invitation, reset, lead
            ->has('notices', 21)
        );
});

it('keeps everyone else out of the notifications page', function (string $role) {
    $this->actingAs(person($role))
        ->get(main('/admin/system/notifications'))
        ->assertForbidden();
})->with(['office_manager', 'hr', 'recruiter']);

it('leaves the top bar’s shared notifications prop alone on every reference page', function (string $path) {
    // A page prop named `notifications` would replace the bell's data and blank the page.
    $this->actingAs(person('admin'))
        ->get(main($path))
        ->assertInertia(fn (Assert $page) => $page->has('notifications.items'));
})->with(['/admin/system', '/admin/system/roles', '/admin/system/notifications', '/admin/system/automations']);

it('shows every scheduled task with its times in Chicago and UTC', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/system/automations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/automations')
            ->has('tasks', count(app(Schedule::class)->events()))
            ->where('tasks.0.name', 'Alert Admin and Payroll about contracts ending in 30 or 14 days')
            ->where('tasks.0.source', 'php artisan contracts:expiration-check')
            ->where('tasks.0.cadence_utc', 'Daily at 07:00')
            ->where('tasks.0.schedule_timezone', 'UTC')
            ->where('tasks.2.source', 'ApplyScheduledContractorCharges (queued job)')
        );
});

it('flags nightly tasks that land in a Chicago evening because they are written in UTC', function () {
    $tasks = collect((new AutomationCatalog)->all())->keyBy('name');

    // 00:15 UTC is 7:15 or 6:15 PM in Chicago, depending on daylight time.
    expect($tasks['Create the upcoming pay weeks'])
        ->evening_in_chicago->toBeTrue()
        ->cadence->toBeIn(['Daily at 6:15 PM', 'Daily at 7:15 PM'])
        ->timeline->toHaveKeys(['chicago', 'utc'])
        // 07:00 UTC is 1 or 2 AM in Chicago: not an evening.
        ->and($tasks['Alert Admin and Payroll about contracts ending in 30 or 14 days']['evening_in_chicago'])->toBeFalse();
});

it('keeps everyone else out of the automations page', function (string $role) {
    $this->actingAs(person($role))
        ->get(main('/admin/system/automations'))
        ->assertForbidden();
})->with(['office_manager', 'payroll', 'recruiter']);

it('shows a recruiter their own access on My Profile', function () {
    $recruiter = person('recruiter');
    $recruiter->update(['muted_notifications' => ['time_tracking']]);
    $property = Property::factory()->create(['name' => 'Acme Hotel']);
    $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    $this->actingAs($recruiter)
        ->get(main('/admin/settings/profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('access.roles', ['Recruiter'])
            ->where('access.properties', ['Acme Hotel'])
            ->where('access.all_properties', false)
            ->where('access.can_count', Permission::role('recruiter')->count())
            ->where('access.total', Permission::query()->count())
            ->where('access.reference_url', null) // only Admin can open the reference
            ->where('access.areas', fn ($areas) => collect($areas)->contains(fn (array $a): bool => $a['name'] === 'Invoices'
                && in_array('Send invoices', $a['can'], true) && in_array('Mark invoices paid', $a['cant'], true)))
            // Areas they have nothing in are left out.
            ->where('access.areas', fn ($areas) => ! collect($areas)->contains(fn (array $a): bool => $a['name'] === 'Inventory'))
            ->where('access.notices', fn ($notices) => collect($notices)->contains(fn (array $n): bool => $n['name'] === 'Punch without verified GPS' && $n['muted'])
                && collect($notices)->contains(fn (array $n): bool => $n['name'] === 'Week approved' && $n['only_if_theirs'] && $n['email'])
                && ! collect($notices)->contains(fn (array $n): bool => $n['name'] === 'Invitation to set a password'))
        );
});

it('shows property managers and contractors their access on QC Minute', function (string $role, string $notice) {
    $this->actingAs(person($role))
        ->get(qcminute('/settings/profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('access.can_count', Permission::role($role)->count())
            ->where('access.notices', fn ($notices) => collect($notices)->contains(fn (array $n): bool => $n['name'] === $notice))
        );
})->with([
    ['property_manager', 'Week waiting for approval'],
    ['contractor', 'Your pay rate is going up'],
]);

it('says email isn’t sent when the person has no email address', function () {
    $contractor = person('contractor');
    $contractor->forceFill(['email' => null])->save();

    $this->actingAs($contractor)
        ->get(qcminute('/settings/profile'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('access.has_email', false)
            ->where('access.notices', fn ($notices) => collect($notices)->every(fn (array $n): bool => ! $n['email']))
        );
});

it('links Admin from My access to their role on the reference', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/settings/profile'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('access.reference_url', '/admin/system/roles?role=admin')
            ->where('access.all_properties', true)
        );
});

it('tells Super Admin about the notices it gets through its permissions', function () {
    $this->actingAs(person('super_admin'))
        ->get(main('/admin/settings/profile'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('access.notices', fn ($notices) => collect($notices)->contains(fn (array $n): bool => $n['name'] === 'Contract expiring' && ! $n['only_if_theirs']))
        );
});
