<?php

namespace App\Domain\Inventory\Enums;

/**
 * Lifecycle of a purchase order (ADR-0012).
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::Draft, self::Ordered], true);
    }
}
