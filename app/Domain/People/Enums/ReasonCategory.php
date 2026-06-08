<?php

namespace App\Domain\People\Enums;

/**
 * The coarse reason a termination was initiated (ADR-0018). Free-text detail
 * lives in the record's `notes`; this is the structured field for reporting.
 */
enum ReasonCategory: string
{
    case Resignation = 'resignation';
    case Performance = 'performance';
    case Conduct = 'conduct';
    case Attendance = 'attendance';
    case Layoff = 'layoff';
    case EndOfContract = 'end_of_contract';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Resignation => 'Resignation',
            self::Performance => 'Performance',
            self::Conduct => 'Conduct',
            self::Attendance => 'Attendance',
            self::Layoff => 'Layoff',
            self::EndOfContract => 'End of Contract',
            self::Other => 'Other',
        };
    }
}
