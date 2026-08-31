<?php

namespace App\Domain\Adjustments\Enums;

/**
 * Lifecycle of a single charge schedule entry (ADR-0014).
 */
enum ChargeEntryStatus: string
{
    case Scheduled = 'scheduled';
    case Applied = 'applied';
    case Skipped = 'skipped';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
