<?php

namespace App\Domain\WorkOrders\Enums;

/**
 * Lifecycle of a more-staff request (ADR-0021). `submitted` → `in_progress` (at
 * least one placement) → `fulfilled` (quantity met); `declined` (recruiter) and
 * `cancelled` (PM / super-admin) are terminal off-ramps.
 */
enum MoreStaffStatus: string
{
    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case Fulfilled = 'fulfilled';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::InProgress => 'In Progress',
            self::Fulfilled => 'Fulfilled',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Open requests still need recruiter action / can accept placements. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::InProgress], true);
    }
}
