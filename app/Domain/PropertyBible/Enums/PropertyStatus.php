<?php

namespace App\Domain\PropertyBible\Enums;

/**
 * Whether QCP is currently servicing a property. Inactive properties don't
 * accept new work orders and are read-only in the Bible (can be reactivated).
 * See 20-domain/property-bible.md "Lifecycle".
 */
enum PropertyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
