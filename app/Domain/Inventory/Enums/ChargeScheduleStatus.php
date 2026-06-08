<?php

namespace App\Domain\Inventory\Enums;

/**
 * Lifecycle of a contractor charge schedule (ADR-0014).
 */
enum ChargeScheduleStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case AcceleratedToFinalPaycheck = 'accelerated_to_final_paycheck';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::AcceleratedToFinalPaycheck => 'Accelerated (final paycheck)',
        };
    }
}
