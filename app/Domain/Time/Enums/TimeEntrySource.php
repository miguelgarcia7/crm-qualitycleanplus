<?php

namespace App\Domain\Time\Enums;

/**
 * Where a time entry came from (ADR-0008, 20-domain/time-tracking.md).
 */
enum TimeEntrySource: string
{
    case ClockEvent = 'clock_event';
    case ManualEntry = 'manual_entry';
    case Imported = 'imported';
    case AdjustmentCredit = 'adjustment_credit';

    public function label(): string
    {
        return match ($this) {
            self::ClockEvent => 'Clock Event',
            self::ManualEntry => 'Manual Entry',
            self::Imported => 'Imported',
            self::AdjustmentCredit => 'Adjustment Credit',
        };
    }
}
