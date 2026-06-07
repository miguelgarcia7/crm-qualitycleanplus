<?php

namespace App\Notifications;

use App\Domain\Billing\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notice that a timesheet moved through its approval lifecycle
 * (submitted / approved / declined). Database channel for now.
 */
class TimesheetStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Timesheet $timesheet,
        public string $event,
        public string $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'timesheet_'.$this->event,
            'timesheet_id' => $this->timesheet->id,
            'property_id' => $this->timesheet->property_id,
            'message' => $this->message,
        ];
    }
}
