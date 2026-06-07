<?php

namespace App\Domain\Time\Enums;

/**
 * Lifecycle of a property's pay week (ADR-0009, 20-domain/time-tracking.md).
 */
enum PayrollPeriodStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case Invoiced = 'invoiced';
    case Closed = 'closed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Can time entries / adjustments be edited? Only while open. */
    public function isEditable(): bool
    {
        return $this === self::Open;
    }
}
