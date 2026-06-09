<?php

namespace App\Domain\Pto\Enums;

/**
 * Why an allotment changed (ADR-0016) — the audit log of every accrual movement.
 * `annual_refresh` opens a new year; `tier_milestone` tops up by the delta when an
 * employee crosses a tenure threshold mid-year; `manual_adjustment` is an HR/admin override.
 */
enum PtoGrantType: string
{
    case AnnualRefresh = 'annual_refresh';
    case TierMilestone = 'tier_milestone';
    case ManualAdjustment = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::AnnualRefresh => 'Annual refresh',
            self::TierMilestone => 'Tier milestone',
            self::ManualAdjustment => 'Manual adjustment',
        };
    }
}
