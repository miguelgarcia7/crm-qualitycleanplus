<?php

namespace App\Notifications;

/**
 * The user-facing notification categories. Each notification class belongs to
 * exactly one; a person can mute a category from My Profile → Notifications
 * (stored in `people.muted_notifications`). In-app (database) is the only
 * delivery channel — there is deliberately no mail/SMS.
 */
enum NotificationCategory: string
{
    case Workflows = 'workflows';
    case Timesheets = 'timesheets';
    case Contracts = 'contracts';
    case TimeTracking = 'time_tracking';

    public function label(): string
    {
        return match ($this) {
            self::Workflows => 'Workflow updates',
            self::Timesheets => 'Timesheets',
            self::Contracts => 'Contract expirations',
            self::TimeTracking => 'Clock-in alerts',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Workflows => 'Pay increases, transfers, staffing requests, and personal-info changes.',
            self::Timesheets => 'Timesheets submitted for your approval, approved, or declined.',
            self::Contracts => 'Property contracts approaching their expiration date.',
            self::TimeTracking => 'QR punches recorded without verified GPS.',
        };
    }
}
