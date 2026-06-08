<?php

namespace App\Domain\Inventory\Enums;

/**
 * Who received an informal (manual) stock outflow (ADR-0015).
 */
enum RecipientType: string
{
    case Contractor = 'contractor';
    case Staff = 'staff';
    case External = 'external';
    case Unspecified = 'unspecified';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
