<?php

namespace App\Notifications;

use App\Domain\PropertyBible\Models\Contract;
use Illuminate\Bus\Queueable;

/**
 * Alerts ownership + payroll that a contract is approaching expiration
 * (20-domain/property-bible.md §4). Database channel for now; email later.
 */
class ContractExpiringNotification extends AppNotification
{
    use Queueable;

    public function __construct(
        public Contract $contract,
        public int $daysUntilExpiration,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Contracts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contract_expiring',
            'category' => $this->category()->value,
            'contract_id' => $this->contract->id,
            'property_id' => $this->contract->property_id,
            'contract_name' => $this->contract->name,
            'days_until_expiration' => $this->daysUntilExpiration,
            'expiration_date' => $this->contract->expiration_date?->toDateString(),
            'message' => "Contract \"{$this->contract->name}\" expires in {$this->daysUntilExpiration} days.",
        ];
    }
}
