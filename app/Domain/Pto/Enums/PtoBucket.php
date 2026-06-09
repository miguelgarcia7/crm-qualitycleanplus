<?php

namespace App\Domain\Pto\Enums;

/**
 * The three strictly-separate PTO pools (ADR-0016). No substitution between buckets —
 * a vacation request can only debit vacation hours.
 */
enum PtoBucket: string
{
    case Vacation = 'vacation';
    case Scheduled = 'scheduled';
    case Unscheduled = 'unscheduled';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Vacation',
            self::Scheduled => 'Scheduled',
            self::Unscheduled => 'Unscheduled',
        };
    }

    /** The allotment column for this bucket on pto_year_allotments. */
    public function allotmentColumn(): string
    {
        return "{$this->value}_allotment";
    }

    /** The delta column for this bucket on pto_grants. */
    public function grantColumn(): string
    {
        return "{$this->value}_hours";
    }
}
