<?php

namespace App\Notifications;

use App\Domain\People\Models\Person;
use Illuminate\Notifications\Notification;

/**
 * Base class for every QCP notification: in-app (database) by default, and
 * silently skipped when the recipient has muted the notification's category.
 *
 * Muting is per category, not per channel — it silences the email as well as
 * the in-app notice, which is what someone turning a category off expects.
 */
abstract class AppNotification extends Notification
{
    abstract public function category(): NotificationCategory;

    /**
     * Channels this notification wants, before muting and addressability are
     * taken into account. Override to reach beyond the in-app notice.
     *
     * @return list<string>
     */
    protected function channels(): array
    {
        return ['database'];
    }

    /**
     * Whether the recipient's category mute applies to this notification.
     *
     * False for transactional messages — a request for action addressed to a
     * named person, where silence breaks a process someone else depends on.
     * Approval systems generally do not let an approver switch off the request
     * itself; the pressure valve is frequency, not silence. The invoice,
     * invitation and reset emails are transactional in the same sense — they
     * simply never extended this class, so they were never mutable.
     */
    protected function mutable(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($this->mutable() && $notifiable instanceof Person && $notifiable->hasMutedNotifications($this->category())) {
            return [];
        }

        return array_values(array_filter(
            $this->channels(),
            // Most legacy contractors identify by phone and have no email at
            // all; mailing them would throw rather than simply not arrive.
            fn (string $channel): bool => $channel !== 'mail' || filled($notifiable->email ?? null),
        ));
    }
}
