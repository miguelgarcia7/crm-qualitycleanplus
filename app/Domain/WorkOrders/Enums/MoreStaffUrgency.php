<?php

namespace App\Domain\WorkOrders\Enums;

/**
 * How urgently a property needs the requested staff (ADR-0021). Drives the
 * recruiter queue's default sort and a visual indicator; no automatic escalation.
 */
enum MoreStaffUrgency: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Sort weight (higher = more urgent) for the recruiter queue. */
    public function weight(): int
    {
        return match ($this) {
            self::Urgent => 3,
            self::High => 2,
            self::Normal => 1,
            self::Low => 0,
        };
    }
}
