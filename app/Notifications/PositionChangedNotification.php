<?php

namespace App\Notifications;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use Illuminate\Bus\Queueable;

/**
 * Tells Admin that someone else (an Office Manager or Payroll) added or renamed
 * a position. A position name is shared by every property, work order and
 * invoice line, so a rename changes it everywhere. Database channel.
 */
class PositionChangedNotification extends AppNotification
{
    use Queueable;

    public function __construct(
        public Position $position,
        public Person $by,
        public ?string $oldName = null,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::PropertyBible;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'position_changed',
            'category' => $this->category()->value,
            'position_id' => $this->position->id,
            'by' => $this->by->name,
            'message' => $this->oldName === null
                ? "{$this->by->name} added the position \"{$this->position->name}\"."
                : "{$this->by->name} renamed the position \"{$this->oldName}\" to \"{$this->position->name}\". The new name shows everywhere it's used.",
        ];
    }
}
