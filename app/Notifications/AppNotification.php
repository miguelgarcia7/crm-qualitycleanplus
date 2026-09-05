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
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Person && $notifiable->hasMutedNotifications($this->category())) {
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
