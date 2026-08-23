<?php

namespace App\Notifications;

use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Support\GpsPolicy;
use Illuminate\Bus\Queueable;

/**
 * In-app notice to a property's recruiters that a QR punch was recorded
 * without verified GPS (GpsPolicy flagged it rather than blocking — the
 * punch stands, a human reviews it).
 */
class PunchFlagged extends AppNotification
{
    use Queueable;

    public function __construct(
        public TimeEntry $entry,
        public string $direction, // 'in' | 'out'
        public string $reason,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::TimeTracking;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $who = $this->entry->person->name;
        $where = $this->entry->property->name;
        $label = GpsPolicy::reasonLabel($this->reason);

        return [
            'type' => 'punch_flagged',
            'category' => $this->category()->value,
            'time_entry_id' => $this->entry->id,
            'property_id' => $this->entry->property_id,
            'message' => "{$who} clocked {$this->direction} at {$where} without verified GPS ({$label}).",
        ];
    }
}
