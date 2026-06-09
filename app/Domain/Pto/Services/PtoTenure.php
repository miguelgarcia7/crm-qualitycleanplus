<?php

namespace App\Domain\Pto\Services;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoTier;
use Carbon\CarbonImmutable;

/**
 * Tenure math for PTO (ADR-0016): the accrual tier for a person as of a date, and
 * the start of their current hire-anniversary PTO year. Tenure is continuous from
 * `hire_date`; a person with no hire date is treated as probation (not yet accruing).
 */
class PtoTenure
{
    public function tierAsOf(Person $person, CarbonImmutable $date): PtoTier
    {
        if ($person->hire_date === null) {
            return PtoTier::Probation;
        }

        $hire = CarbonImmutable::parse($person->hire_date)->startOfDay();

        return match (true) {
            $date->lt($hire->addDays(30)) => PtoTier::Probation,
            $date->lt($hire->addMonths(6)) => PtoTier::Tier1,
            $date->lt($hire->addYear()) => PtoTier::Tier2,
            default => PtoTier::Tier3,
        };
    }

    /** Start of the hire-anniversary year that contains $date (most recent anniversary on/before it). */
    public function anniversaryStart(Person $person, CarbonImmutable $date): CarbonImmutable
    {
        $hire = CarbonImmutable::parse($person->hire_date)->startOfDay();

        if ($date->lte($hire)) {
            return $hire;
        }

        $years = (int) $hire->diffInYears($date);
        $start = $hire->addYears($years);

        return $start->gt($date) ? $start->subYear() : $start;
    }
}
