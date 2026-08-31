<?php

namespace App\Domain\Adjustments\Enums;

/**
 * Why a contractor is being charged. Both are payroll deductions and never
 * billed to the property; they differ in origin and in how the schedule is
 * built.
 */
enum ChargeReason: string
{
    /** Issued uniform or equipment, from a fulfilled supply request (ADR-0014). */
    case Uniform = 'uniform';

    /** QCP's fee for taking a contractor on, set when they are promoted. */
    case HiringFee = 'hiring_fee';

    public function label(): string
    {
        return match ($this) {
            self::Uniform => 'Uniform charge',
            self::HiringFee => 'Hiring fee',
        };
    }
}
