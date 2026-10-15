<?php

namespace App\Notifications;

/**
 * The user-facing notification categories. Each notification class belongs to
 * exactly one; a person can mute a category from My Profile → Notifications
 * (stored in `people.muted_notifications`). Muting a category silences both
 * the in-app notice and any email that belongs to it. There is no SMS.
 */
enum NotificationCategory: string
{
    case Workflows = 'workflows';
    case Timesheets = 'timesheets';
    case Contracts = 'contracts';
    case TimeTracking = 'time_tracking';
    case Applications = 'applications';
    case PropertyBible = 'property_bible';
    case Schedules = 'schedules';

    public function label(): string
    {
        return match ($this) {
            self::Workflows => 'Workflow updates',
            self::Timesheets => 'Timesheets',
            self::Contracts => 'Contracts',
            self::TimeTracking => 'Clock-in alerts',
            self::Applications => 'New applications',
            self::PropertyBible => 'Property Bible changes',
            self::Schedules => 'Hotel schedules',
        };
    }

    /**
     * Whether this category also sends email, so the preferences screen can
     * say that muting it silences more than the bell.
     *
     * Timesheets is the only one: TimesheetAwaitingApproval and
     * TimesheetDecided (phase 09g). Muting is per category, not per channel,
     * so switching it off stops both.
     */
    public function sendsEmail(): bool
    {
        return $this === self::Timesheets;
    }

    public function description(): string
    {
        return match ($this) {
            self::Workflows => 'Pay increases, transfers, staffing requests, and personal-info changes.',
            self::Timesheets => 'Outcomes on timesheets you submitted — approved or declined. Requests to approve a week always arrive; they are waiting on your decision.',
            self::Contracts => 'Property contracts approaching their expiration date, or turning three years old.',
            self::TimeTracking => 'QR punches recorded without verified GPS.',
            self::Applications => 'Each job application submitted through the website.',
            self::PropertyBible => 'Positions added or renamed by someone other than an Admin.',
            self::Schedules => 'A property manager uploaded the schedule for a work week at one of your hotels.',
        };
    }
}
