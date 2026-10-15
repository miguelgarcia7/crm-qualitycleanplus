<?php

namespace App\Notifications;

use App\Domain\PropertyBible\Models\Contract;
use Illuminate\Bus\Queueable;

/**
 * Tells Admin a property contract turned three years old, counted from its
 * effective date (or the day it was uploaded, when it has none). Contracts are
 * kept, never deleted; this is a nudge to review it. Database channel.
 */
class ContractThreeYearsOldNotification extends AppNotification
{
    use Queueable;

    public function __construct(public Contract $contract) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Contracts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $since = $this->contract->effective_date ?? $this->contract->created_at;
        $property = $this->contract->property?->name;

        return [
            'type' => 'contract_three_years',
            'category' => $this->category()->value,
            'contract_id' => $this->contract->id,
            'property_id' => $this->contract->property_id,
            'contract_name' => $this->contract->name,
            'since' => $since?->toDateString(),
            'message' => "Contract \"{$this->contract->name}\"".($property ? " ({$property})" : '').' is 3 years old'
                .($since ? ' (since '.$since->format('M j, Y').')' : '').'. Time to review it.',
        ];
    }
}
