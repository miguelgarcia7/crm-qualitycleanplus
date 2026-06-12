<?php

use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\Workflows\Models\Workflow;
use App\Notifications\TimesheetStatusChanged;
use App\Notifications\WorkflowNotice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Insert a stored database notification directly — the read side doesn't care who sent it. */
function storedNotice(Person $person, array $data = [], bool $read = false): string
{
    $notification = $person->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => WorkflowNotice::class,
        'data' => array_merge([
            'type' => 'workflow_pay_increase',
            'category' => 'workflows',
            'message' => 'A pay increase was approved.',
        ], $data),
        'read_at' => $read ? now() : null,
    ]);

    return $notification->id;
}

// --- Bell payload (shared Inertia prop) ---------------------------------------

it('shares the bell payload with unread count and recent items', function () {
    $admin = person('admin');
    storedNotice($admin);
    storedNotice($admin, ['message' => 'Older, already seen.'], read: true);

    $this->actingAs($admin)->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.base', '/admin/notifications')
            ->has('notifications.items', 2),
        );
});

// --- Notification center ---------------------------------------------------------

it('renders the notification center with the full history', function () {
    $admin = person('admin');
    storedNotice($admin);
    storedNotice($admin, ['message' => 'Second notice.']);

    $this->actingAs($admin)->get(main('/admin/notifications'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/notifications/index')
            ->has('history', 2),
        );
});

it('marks read and deep-links when opening a notification', function () {
    $admin = person('admin');
    $id = storedNotice($admin, ['type' => 'timesheet_approved', 'category' => 'timesheets', 'timesheet_id' => 5]);

    $this->actingAs($admin)->get(main("/admin/notifications/{$id}/open"))
        ->assertRedirect(main('/admin/timesheets'));

    expect($admin->notifications()->find($id)->read_at)->not->toBeNull();
});

it('falls back to the previous page when a notification has no deep link', function () {
    $admin = person('admin');
    $id = storedNotice($admin, ['type' => 'workflow_termination']);

    $this->actingAs($admin)
        ->from(main('/admin/dashboard'))
        ->get(main("/admin/notifications/{$id}/open"))
        ->assertRedirect(main('/admin/dashboard'));

    expect($admin->notifications()->find($id)->read_at)->not->toBeNull();
});

it('marks all notifications read at once', function () {
    $admin = person('admin');
    storedNotice($admin);
    storedNotice($admin);
    storedNotice($admin);

    $this->actingAs($admin)
        ->from(main('/admin/dashboard'))
        ->post(main('/admin/notifications/read-all'))
        ->assertRedirect(main('/admin/dashboard'));

    expect($admin->unreadNotifications()->count())->toBe(0);
});

it("cannot touch another person's notifications", function () {
    $admin = person('admin');
    $other = person('hr');
    $id = storedNotice($other);

    $this->actingAs($admin)->post(main("/admin/notifications/{$id}/read"))->assertNotFound();

    expect($other->unreadNotifications()->count())->toBe(1);
});

// --- Mute preferences gate delivery ---------------------------------------------

it('delivers a workflow notice in-app by default', function () {
    $recruiter = person('recruiter');
    $workflow = Workflow::factory()->create();

    $recruiter->notify(new WorkflowNotice($workflow, 'A transfer needs your attention.'));

    expect($recruiter->notifications()->count())->toBe(1)
        ->and($recruiter->notifications()->first()->data['category'])->toBe('workflows');
});

it('skips delivery entirely for a muted category', function () {
    $recruiter = person('recruiter');
    $recruiter->update(['muted_notifications' => ['workflows']]);
    $workflow = Workflow::factory()->create();

    $recruiter->notify(new WorkflowNotice($workflow, 'A transfer needs your attention.'));

    expect($recruiter->notifications()->count())->toBe(0);
});

it('still delivers other categories when one is muted', function () {
    $recruiter = person('recruiter');
    $recruiter->update(['muted_notifications' => ['workflows']]);
    $timesheet = Timesheet::factory()->create();

    $recruiter->notify(new TimesheetStatusChanged($timesheet, 'approved', 'Your timesheet was approved.'));

    expect($recruiter->notifications()->count())->toBe(1)
        ->and($recruiter->notifications()->first()->data['category'])->toBe('timesheets');
});

// --- Preference settings -----------------------------------------------------------

it('shows notification settings on the profile page', function () {
    $this->actingAs(person('admin'))->get(main('/admin/settings/profile'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('notificationSettings.categories', 3)
            ->where('notificationSettings.muted', []),
        );
});

it('saves muted categories from the profile notifications tab', function () {
    $admin = person('admin');

    $this->actingAs($admin)
        ->patch(main('/admin/settings/notifications'), ['muted' => ['workflows', 'contracts']])
        ->assertRedirect(main('/admin/settings/profile'));

    expect($admin->fresh()->muted_notifications)->toBe(['workflows', 'contracts']);
});

it('rejects unknown notification categories', function () {
    $this->actingAs(person('admin'))
        ->patch(main('/admin/settings/notifications'), ['muted' => ['carrier-pigeon']])
        ->assertSessionHasErrors('muted.0');
});

// --- QC Minute surface ----------------------------------------------------------------

it('serves the bell and history on QC Minute with surface-relative links', function () {
    $pm = person('property_manager');
    $id = storedNotice($pm, ['type' => 'timesheet_submitted', 'category' => 'timesheets', 'timesheet_id' => 9]);

    $this->actingAs($pm)->get(qcminute('/'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.base', '/notifications'),
        );

    $this->actingAs($pm)->get(qcminute('/notifications'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('history', 1));

    $this->actingAs($pm)->get(qcminute("/notifications/{$id}/open"))
        ->assertRedirect(qcminute('/timesheets/9'));
});
