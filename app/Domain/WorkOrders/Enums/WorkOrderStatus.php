<?php

namespace App\Domain\WorkOrders\Enums;

/**
 * Lifecycle of a work order (20-domain/work-orders.md).
 */
enum WorkOrderStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Suspended => 'Suspended',
        };
    }

    /** Can this WO accept new time entries? */
    public function acceptsTime(): bool
    {
        return $this === self::Active;
    }
}
