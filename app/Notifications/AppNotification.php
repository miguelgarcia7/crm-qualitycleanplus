<?php

namespace App\Notifications;

use App\Domain\People\Models\Person;
use Illuminate\Notifications\Notification;

/**
 * Base class for every QCP notification: in-app (database) only, and silently
 * skipped when the recipient has muted the notification's category.
 */
abstract class AppNotification extends Notification
{
    abstract public function category(): NotificationCategory;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof Person && $notifiable->hasMutedNotifications($this->category())) {
            return [];
        }

        return ['database'];
    }
}
