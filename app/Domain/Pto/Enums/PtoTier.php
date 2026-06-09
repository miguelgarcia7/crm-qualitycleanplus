<?php

namespace App\Domain\Pto\Enums;

/**
 * Tenure-based PTO accrual tier (ADR-0016). Each tier grants the same number of
 * hours to all three buckets; tier_3 (12 months) is the ceiling.
 */
enum PtoTier: string
{
    case Probation = 'probation';  // day 1–30
    case Tier1 = 'tier_1';         // ≥ 30 days  (50%)
    case Tier2 = 'tier_2';         // ≥ 6 months (75%)
    case Tier3 = 'tier_3';         // ≥ 12 months (100%)

    /** Hours granted to each bucket at this tier. */
    public function hoursPerBucket(): int
    {
        return match ($this) {
            self::Probation => 0,
            self::Tier1 => 20,
            self::Tier2 => 30,
            self::Tier3 => 40,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Probation => 'Probation',
            self::Tier1 => '30 days (50%)',
            self::Tier2 => '6 months (75%)',
            self::Tier3 => '12 months (100%)',
        };
    }
}
