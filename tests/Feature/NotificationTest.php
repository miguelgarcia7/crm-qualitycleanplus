<?php

use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Actions\DeclineTimesheet;
use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Models\Workflow;
use App\Notifications\TimesheetAwaitingApproval;
use App\Notifications\TimesheetDecided;
use App\Notifications\TimesheetStatusChanged;
use App\Notifications\WorkflowNotice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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

/** A submittable timesheet, its recruiter, and a PM assigned to the property. */
function notifyScenario(): array
{
    $property = Property::factory()->create(['name' => 'Sunrise Villas']);
    $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeek();

    $period = PayrollPeriod::factory()->forWeek($monday)->create(['property_id' => $property->id]);
    $timesheet = Timesheet::factory()->create([
        'property_id' => $property->id,
        'payroll_period_id' => $period->id,
        'status' => TimesheetStatus::Draft,
    ]);

    $pm = person('property_manager');
    $property->assignments()->create(['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

    return ['property' => $property, 'timesheet' => $timesheet, 'pm' => $pm, 'recruiter' => person('recruiter')];
}
// --- Bell payload (shared Inertia prop) ---------------------------------------

it('shares the bell payload with unread count and recent items', function () {
    $admin = person('admin');
    storedNotice($admin);
    storedNotice($admin, ['message' => 'Older, already seen.'], read: true);

    $this->actingAs($admin)->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('surface', 'backoffice')
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
            ->has('notificationSettings.categories', 4)
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
            ->where('surface', 'qcminute')
            ->where('notifications.unread', 1)
            ->where('notifications.base', '/notifications'),
        );

    $this->actingAs($pm)->get(qcminute('/notifications'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('history', 1));

    $this->actingAs($pm)->get(qcminute("/notifications/{$id}/open"))
        ->assertRedirect(qcminute('/timesheets/9'));
});

// --- Timesheet submitted: email alongside the in-app notice -------------------------

it('emails every property manager when a week is submitted', function () {
    Notification::fake();
    $s = notifyScenario();

    app(SubmitTimesheetForApproval::class)->handle($s['timesheet'], $s['recruiter']);

    // Both go out: the in-app notice immediately, the email on the queue.
    Notification::assertSentTo($s['pm'], TimesheetStatusChanged::class);
    Notification::assertSentTo($s['pm'], TimesheetAwaitingApproval::class);
});

it('queues the email so a mail outage cannot fail a submit that already happened', function () {
    expect(new TimesheetAwaitingApproval(Timesheet::factory()->make()))
        ->toBeInstanceOf(ShouldQueue::class);
});

it('points the property manager at QC Minute, not the back office they cannot reach', function () {
    $s = notifyScenario();
    $mail = (new TimesheetAwaitingApproval($s['timesheet']))->toMail($s['pm']);

    $host = config('domains.qcminute');

    expect($mail->actionUrl)->toContain("//{$host}/timesheets/{$s['timesheet']->id}")
        ->and($mail->actionUrl)->not->toContain(config('domains.main'))
        // The stock header links to APP_URL; anonymous Blade components have
        // isolated scope, so the override rides on viewData.
        ->and($mail->viewData['headerUrl'])->toContain($host);
});

it('sends no email to a property manager with no address on file', function () {
    $s = notifyScenario();
    $s['pm']->update(['email' => null]);

    // Most legacy people identify by phone; mailing them would throw.
    expect((new TimesheetAwaitingApproval($s['timesheet']))->via($s['pm']))->toBe([]);
});

it('muting the timesheets category silences the email as well as the notice', function () {
    $s = notifyScenario();
    $s['pm']->update(['muted_notifications' => ['timesheets']]);

    expect((new TimesheetAwaitingApproval($s['timesheet']))->via($s['pm']))->toBe([])
        ->and((new TimesheetStatusChanged($s['timesheet'], 'submitted', 'x'))->via($s['pm']))->toBe([]);
});

it('renders a real email carrying the property, the week and the review link', function () {
    $s = notifyScenario();

    // Actually render through the mailer — a MailMessage that looks right can
    // still blow up in the Blade layer, which is how the invoice header bug hid.
    $s['pm']->notifyNow(new TimesheetAwaitingApproval($s['timesheet']));

    /** @var ArrayTransport $transport */
    $transport = Mail::mailer()->getSymfonyTransport();
    $messages = $transport->messages();
    expect($messages)->toHaveCount(1);

    $body = $messages[0]->toString();
    $host = config('domains.qcminute');

    expect($body)->toContain('Sunrise Villas')
        ->and($body)->toContain('waiting for your approval')
        ->and($body)->toContain("//{$host}/timesheets/{$s['timesheet']->id}")
        // The header must not send a PM to the back office they cannot sign in to.
        ->and($body)->not->toContain('//'.config('domains.main'));
});

// --- Timesheet decided: the recruiter hears back ------------------------------------

it('emails the recruiter when their week is approved, pointing at the invoice', function () {
    $s = notifyScenario();
    app(SubmitTimesheetForApproval::class)->handle($s['timesheet'], $s['recruiter']);

    /** @var ArrayTransport $transport */
    $transport = Mail::mailer()->getSymfonyTransport();
    // Submitting already sent the PM's email; clear it so this asserts on ours.
    $transport->flush();

    $s['recruiter']->notifyNow(new TimesheetDecided($s['timesheet']->fresh(), approved: true));
    $body = $transport->messages()[0]->getOriginalMessage()->toString();

    expect($body)->toContain('Sunrise Villas')
        ->toContain('has been approved')
        ->toContain('ready to send')
        // Back office, not QC Minute — this recipient is staff.
        ->toContain('//'.config('domains.main').'/admin/')
        ->not->toContain('//'.config('domains.qcminute'));
});

it('carries the decline reason and links to the grid that needs fixing', function () {
    $s = notifyScenario();
    app(SubmitTimesheetForApproval::class)->handle($s['timesheet'], $s['recruiter']);
    app(DeclineTimesheet::class)->handle($s['timesheet']->fresh(), $s['pm'], 'Tuesday hours look wrong', 'hours');

    /** @var ArrayTransport $transport */
    $transport = Mail::mailer()->getSymfonyTransport();
    $transport->flush();

    $s['recruiter']->notifyNow(new TimesheetDecided($s['timesheet']->fresh(), approved: false));
    // The decoded body, not toString(): quoted-printable encoding turns the
    // "=" in "?week=" into "=3D", so a URL assertion on the raw message fails.
    $body = (string) $transport->messages()[0]->getOriginalMessage()->getHtmlBody();
    $week = $s['timesheet']->payrollPeriod->week_start->toDateString();

    expect($body)->toContain('was declined')
        // The reason is the whole point of the email.
        ->toContain('Tuesday hours look wrong')
        ->toContain('reopened')
        // Straight to the grid they have to correct, not a list to search.
        ->toContain("/admin/properties/{$s['property']->id}/grid?week={$week}");
});

it('sends both decision emails through the queue, like the submission one', function () {
    $timesheet = Timesheet::factory()->make();

    expect(new TimesheetDecided($timesheet, approved: true))->toBeInstanceOf(ShouldQueue::class)
        ->and(new TimesheetDecided($timesheet, approved: false))->toBeInstanceOf(ShouldQueue::class);
});

it('fires the approval email from the action, alongside the in-app notice', function () {
    Notification::fake();
    $s = notifyScenario();
    app(SubmitTimesheetForApproval::class)->handle($s['timesheet'], $s['recruiter']);

    app(ApproveTimesheet::class)->handle($s['timesheet']->fresh(), $s['pm']);

    Notification::assertSentTo($s['recruiter'], TimesheetStatusChanged::class);
    Notification::assertSentTo($s['recruiter'], TimesheetDecided::class);
});

it('fires the decline email from the action, alongside the in-app notice', function () {
    Notification::fake();
    $s = notifyScenario();
    app(SubmitTimesheetForApproval::class)->handle($s['timesheet'], $s['recruiter']);

    app(DeclineTimesheet::class)->handle($s['timesheet']->fresh(), $s['pm'], 'Hours look wrong', 'hours');

    Notification::assertSentTo($s['recruiter'], TimesheetDecided::class);
});

it('tells the preferences screen which categories reach beyond the bell', function () {
    $this->actingAs(person('admin'))->get(main('/admin/settings/profile'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $categories = collect($page->toArray()['props']['notificationSettings']['categories']);

            // The screen said muting "only mutes the bell", which stopped being
            // true when the timesheet cycle started emailing.
            expect($categories->firstWhere('value', 'timesheets')['emails'])->toBeTrue()
                ->and($categories->firstWhere('value', 'workflows')['emails'])->toBeFalse()
                ->and($categories->firstWhere('value', 'contracts')['emails'])->toBeFalse()
                ->and($categories->firstWhere('value', 'time_tracking')['emails'])->toBeFalse();
        });
});
