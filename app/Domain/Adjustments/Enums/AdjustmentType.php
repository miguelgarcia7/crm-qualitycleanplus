<?php

namespace App\Domain\Adjustments\Enums;

/**
 * Whether an adjustment adds to or subtracts from a contractor's pay
 * (20-domain/adjustments.md).
 */
enum AdjustmentType: string
{
    /** Adds to pay; may be billable (flows to the invoice) or payroll-only. */
    case Incentive = 'incentive';
    /** Reduces pay; never billable. */
    case Deduction = 'deduction';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Signed multiplier for net-pay math. */
    public function sign(): int
    {
        return $this === self::Incentive ? 1 : -1;
    }
}
