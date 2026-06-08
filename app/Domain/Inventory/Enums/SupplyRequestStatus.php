<?php

namespace App\Domain\Inventory\Enums;

/**
 * Lifecycle of a supply request (ADR-0012).
 */
enum SupplyRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Fulfilled = 'fulfilled';
    case Denied = 'denied';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
