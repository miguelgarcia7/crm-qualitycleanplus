<?php

namespace App\Domain\SystemReference\Support;

use App\Domain\Recruiting\Actions\NotifyStaffOfApplication;
use App\Http\Controllers\UserInviteController;
use App\Notifications\ApplicationReceived;
use App\Notifications\AppNotification;
use App\Notifications\ContactInquiryReceived;
use App\Notifications\ContractExpiringNotification;
use App\Notifications\DirectHireEligible;
use App\Notifications\InvoiceIssued;
use App\Notifications\PasswordResetLink;
use App\Notifications\PunchFlagged;
use App\Notifications\TimesheetAwaitingApproval;
use App\Notifications\TimesheetDecided;
use App\Notifications\TimesheetStatusChanged;
use App\Notifications\UserInvitation;
use App\Notifications\WorkflowNotice;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use ReflectionClass;
use Spatie\Permission\Models\Permission;

/**
 * Every notice the system sends, as Admin reads it on the System reference:
 * what it says, when it goes, who gets it and how.
 *
 * Split by what code can and can't tell us. The wording, the trigger and the
 * recipients are decided where each notice is sent, so they're written here.
 * How it's delivered (in-app, email), whether it can be muted and its mute
 * category are asked of the notification class itself; recipients that follow
 * a permission or a role list are read from that. A test checks every
 * notification class appears here, so a new one can't go missing.
 */
class NotificationCatalog
{
    /** Pseudo-role for addresses that aren't logins (a property's billing email, the lead inboxes). */
    public const OUTSIDE = 'outside';

    /**
     * @return list<array{id: string, group: string, name: string, summary: string, what: string, when: string, who: string,
     *     classes: list<string>, in_app: bool, email: bool, can_mute: bool, mute_category: string|null,
     *     audience: array<string, 'always'|'if_theirs'>}>
     */
    public function all(): array
    {
        return array_map(fn (array $entry): array => $this->resolve($entry), $this->entries());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(): array
    {
        $threshold = number_format((int) config('qcp.work_orders.direct_hire_threshold_hours'));

        return [
            [
                'id' => 'timesheet-submitted', 'group' => 'Timesheets', 'name' => 'Week waiting for approval',
                'summary' => 'The recruiter sent the week',
                'what' => 'Tells the property manager a week of hours is ready to approve on QC Minute.',
                'when' => 'Someone sends a week from the timesheet grid.',
                'who' => 'The property managers assigned to that property.',
                'classes' => [TimesheetStatusChanged::class => ['event' => 'submitted'], TimesheetAwaitingApproval::class => []],
                'always' => ['property_manager'],
            ],
            [
                'id' => 'timesheet-approved', 'group' => 'Timesheets', 'name' => 'Week approved',
                'summary' => 'The invoice is created',
                'what' => 'Tells whoever sent the week that it was approved. The invoice is created at the same moment.',
                'when' => 'A property manager approves a week.',
                'who' => 'Whoever sent the week, usually the property’s recruiter.',
                'classes' => [TimesheetStatusChanged::class => ['event' => 'approved'], TimesheetDecided::class => []],
                'if_theirs' => 'timesheets.submit_for_approval',
            ],
            [
                'id' => 'timesheet-declined', 'group' => 'Timesheets', 'name' => 'Week declined',
                'summary' => 'With the property manager’s reason',
                'what' => 'Tells whoever sent the week that it was sent back, with the property manager’s reason.',
                'when' => 'A property manager declines a week.',
                'who' => 'Whoever sent the week, usually the property’s recruiter.',
                'classes' => [TimesheetStatusChanged::class => ['event' => 'declined'], TimesheetDecided::class => []],
                'if_theirs' => 'timesheets.submit_for_approval',
            ],
            [
                'id' => 'invoice-sent', 'group' => 'Billing', 'name' => 'Invoice emailed',
                'summary' => 'Link to view it on QC Minute',
                'what' => 'Sends the invoice to the property as a link. The PDF stays behind the QC Minute login.',
                'when' => 'Someone clicks Send on an invoice.',
                'who' => 'The billing email saved on the property. It isn’t a login.',
                'classes' => [InvoiceIssued::class => []],
                'outside' => true,
            ],
            [
                'id' => 'punch-flagged', 'group' => 'Clock-in', 'name' => 'Punch without verified GPS',
                'summary' => 'Flagged for review',
                'what' => 'Flags a QR clock-in or clock-out that had no confirmed location. The punch still counts.',
                'when' => 'A contractor punches by QR and GPS fails or is turned off.',
                'who' => 'The recruiters assigned to that property.',
                'classes' => [PunchFlagged::class => []],
                'always' => ['recruiter'],
            ],
            [
                'id' => 'staffing-new', 'group' => 'Staffing requests', 'name' => 'New staffing request',
                'summary' => 'To the assigned recruiter',
                'what' => 'A property asked for more people.',
                'when' => 'Someone submits a staffing request.',
                'who' => 'The recruiter assigned to the request.',
                'classes' => [WorkflowNotice::class => []],
                'always' => ['recruiter'],
            ],
            [
                'id' => 'staffing-cancelled', 'group' => 'Staffing requests', 'name' => 'Staffing request cancelled',
                'summary' => 'To the assigned recruiter',
                'what' => 'A staffing request was withdrawn.',
                'when' => 'Whoever made the request cancels it.',
                'who' => 'The recruiter assigned to the request.',
                'classes' => [WorkflowNotice::class => []],
                'always' => ['recruiter'],
            ],
            [
                'id' => 'staffing-declined', 'group' => 'Staffing requests', 'name' => 'Staffing request declined',
                'summary' => 'With the reason, to whoever asked',
                'what' => 'The staffing request won’t be filled, and why.',
                'when' => 'A recruiter or Admin declines the request.',
                'who' => 'Whoever made the request, usually the property manager.',
                'classes' => [WorkflowNotice::class => []],
                'if_theirs' => 'workflows.more_staff.initiate',
            ],
            [
                'id' => 'staffing-placed', 'group' => 'Staffing requests', 'name' => 'Staff placed or request filled',
                'summary' => 'Progress, then completion',
                'what' => 'Each placement against the request, then a final notice when every spot is filled.',
                'when' => 'A recruiter records a placement.',
                'who' => 'Whoever made the request, usually the property manager.',
                'classes' => [WorkflowNotice::class => []],
                'if_theirs' => 'workflows.more_staff.initiate',
            ],
            [
                'id' => 'pay-raised', 'group' => 'Pay increases', 'name' => 'Your pay rate is going up',
                'summary' => 'New hourly rate and start date',
                'what' => 'Tells the contractor their new pay rate and when it starts.',
                'when' => 'A pay increase is approved.',
                'who' => 'The contractor.',
                'classes' => [WorkflowNotice::class => []],
                'always' => ['contractor'],
            ],
            [
                'id' => 'pay-approved', 'group' => 'Pay increases', 'name' => 'Pay increase approved',
                'summary' => 'New bill rate, to whoever asked',
                'what' => 'Confirms the increase and the new bill rate.',
                'when' => 'A recruiter or Admin approves the increase.',
                'who' => 'Whoever asked for it, usually the property manager.',
                'classes' => [WorkflowNotice::class => []],
                'if_theirs' => 'workflows.pay_increase.initiate',
            ],
            [
                'id' => 'pay-declined', 'group' => 'Pay increases', 'name' => 'Pay increase declined',
                'summary' => 'With the reason, to whoever asked',
                'what' => 'The pay increase won’t go ahead, and why.',
                'when' => 'A recruiter or Admin declines the increase.',
                'who' => 'Whoever asked for it, usually the property manager.',
                'classes' => [WorkflowNotice::class => []],
                'if_theirs' => 'workflows.pay_increase.initiate',
            ],
            [
                'id' => 'contractor-transferred', 'group' => 'Contractor moves', 'name' => 'Contractor transferred to you',
                'summary' => 'To the new recruiter',
                'what' => 'A contractor joined this recruiter’s roster.',
                'when' => 'A transfer is completed.',
                'who' => 'The contractor’s new recruiter.',
                'classes' => [WorkflowNotice::class => []],
                'always' => ['recruiter'],
            ],
            [
                'id' => 'temporary-assignment', 'group' => 'Contractor moves', 'name' => 'Temporary assignment started',
                'summary' => 'Recruiters at that property',
                'what' => 'A contractor is working at another property for a while, until a set end date.',
                'when' => 'A temporary assignment is opened.',
                'who' => 'The recruiters assigned to the temporary property.',
                'classes' => [WorkflowNotice::class => []],
                'always' => ['recruiter'],
            ],
            [
                'id' => 'direct-hire', 'group' => 'Contractor moves', 'name' => 'Direct-hire hours reached',
                'summary' => "{$threshold} hours at one property by default",
                'what' => 'The property could now hire this contractor directly, so the recruiter should talk to them first.',
                'when' => "The contractor’s hours at one property pass the work order’s direct-hire threshold ({$threshold} hours unless the contract sets another).",
                'who' => 'The contractor’s recruiter and the recruiters assigned to the property.',
                'classes' => [DirectHireEligible::class => []],
                'always' => ['recruiter'],
            ],
            [
                'id' => 'personal-info', 'group' => 'People', 'name' => 'Personal-info change decided',
                'summary' => 'Approved or declined',
                'what' => 'Tells the person whether their change to their own details was approved and applied, or declined and why.',
                'when' => 'HR or Admin verifies the change request.',
                'who' => 'The person who asked for the change.',
                'classes' => [WorkflowNotice::class => []],
                'if_theirs' => 'people.own_profile.request_change',
            ],
            [
                'id' => 'application', 'group' => 'People', 'name' => 'New application from the website',
                'summary' => 'Someone applied to a job posting',
                'what' => 'Someone applied through the public job board.',
                'when' => 'An application is submitted on the website.',
                'who' => 'Every recruiter and office manager.',
                'classes' => [ApplicationReceived::class => []],
                'always' => NotifyStaffOfApplication::ROLES,
            ],
            [
                'id' => 'contract-expiring', 'group' => 'Contracts', 'name' => 'Contract expiring',
                'summary' => '30 and 14 days before it ends',
                'what' => 'A property contract is close to its end date.',
                'when' => 'The nightly check finds a contract ending in 30 or 14 days.',
                'who' => 'Everyone who can view contracts.',
                'classes' => [ContractExpiringNotification::class => []],
                'always' => 'bible.contracts.view',
            ],
            [
                'id' => 'invitation', 'group' => 'Accounts', 'name' => 'Invitation to set a password',
                'summary' => 'Sent when a login is created',
                'what' => 'Welcomes a new login and lets them choose a password.',
                'when' => 'Someone creates a login from People → Invite.',
                'who' => 'The new person.',
                'classes' => [UserInvitation::class => []],
                'if_theirs' => array_keys(UserInviteController::INVITABLE_ROLES),
            ],
            [
                'id' => 'password-reset', 'group' => 'Accounts', 'name' => 'Password reset link',
                'summary' => 'Anyone with an email address',
                'what' => 'A link to choose a new password, pointed at the site the person signs in on.',
                'when' => 'Someone clicks “Forgot password” and enters their email.',
                'who' => 'The person who asked. People without an email address, like most contractors, can’t receive it.',
                'classes' => [PasswordResetLink::class => []],
                'if_theirs' => array_values(array_diff(array_keys(PermissionCatalog::ROLE_LABELS), ['super_admin'])),
            ],
            [
                'id' => 'contact-lead', 'group' => 'Marketing site', 'name' => 'Contact form lead',
                'summary' => 'Job seeker or business',
                'what' => 'A message from the website’s contact form. Replying answers the visitor.',
                'when' => 'Someone submits the contact form on the marketing site.',
                'who' => 'The inboxes set for website leads. The lead is saved under Contact Inquiries either way.',
                'classes' => [ContactInquiryReceived::class => []],
                'outside' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function resolve(array $entry): array
    {
        $channels = [];
        $canMute = true;
        $category = null;

        /** @var array<class-string<Notification>, array<string, mixed>> $classes */
        $classes = $entry['classes'];
        foreach ($classes as $class => $props) {
            $notification = $this->instance($class, $props);

            if ($notification instanceof AppNotification) {
                $channels = [...$channels, ...$this->call($notification, 'channels')];
                $canMute = $canMute && $this->call($notification, 'mutable');
                $category ??= $notification->category()->label();
            } elseif (method_exists($notification, 'via')) {
                // Addressed to an email, not to a person's preferences: never mutable.
                $channels = [...$channels, ...$notification->via(new AnonymousNotifiable)];
                $canMute = false;
            }
        }

        $audience = [];
        foreach ($this->roles($entry['always'] ?? []) as $role) {
            $audience[$role] = 'always';
        }
        foreach ($this->roles($entry['if_theirs'] ?? []) as $role) {
            $audience[$role] ??= 'if_theirs';
        }
        if ($entry['outside'] ?? false) {
            $audience[self::OUTSIDE] = 'always';
        }

        return [
            'id' => $entry['id'],
            'group' => $entry['group'],
            'name' => $entry['name'],
            'summary' => $entry['summary'],
            'what' => $entry['what'],
            'when' => $entry['when'],
            'who' => $entry['who'],
            'classes' => array_map(fn (string $class): string => class_basename($class), array_keys($classes)),
            'in_app' => in_array('database', $channels, true),
            'email' => in_array('mail', $channels, true),
            'can_mute' => $canMute,
            'mute_category' => $canMute ? $category : null,
            'audience' => $audience,
        ];
    }

    /**
     * A role list, or the roles holding a permission (read live, so a change
     * in RolePermissionSeeder shows up here). Super Admin isn't a column.
     *
     * @param  string|list<string>  $source
     * @return list<string>
     */
    private function roles(string|array $source): array
    {
        $roles = is_string($source)
            ? (Permission::query()->where('name', $source)->first()?->roles->pluck('name')->all() ?? [])
            : $source;

        return array_values(array_filter($roles, fn (string $role): bool => $role !== 'super_admin'));
    }

    /**
     * The class without its constructor (its arguments are models from a real
     * send), with just the properties its delivery rules read.
     *
     * @param  class-string<Notification>  $class
     * @param  array<string, mixed>  $props
     */
    private function instance(string $class, array $props): Notification
    {
        $notification = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        foreach ($props as $name => $value) {
            (function () use ($name, $value): void {
                $this->{$name} = $value;
            })->call($notification);
        }

        return $notification;
    }

    /** Calls one of the class's own (protected) delivery rules. */
    private function call(Notification $notification, string $method): mixed
    {
        return (fn () => $this->{$method}())->call($notification);
    }
}
