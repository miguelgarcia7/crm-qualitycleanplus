<?php

namespace App\Domain\Pto\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoAllotmentStatus;
use App\Domain\Pto\Enums\PtoGrantType;
use App\Domain\Pto\Models\PtoGrant;
use App\Domain\Pto\Models\PtoYearAllotment;
use App\Domain\Pto\Services\PtoTenure;
use Carbon\CarbonImmutable;

/**
 * Ensures the person's current hire-anniversary PTO year exists (ADR-0016).
 * Idempotent — returns the existing open allotment, or opens one at the person's
 * **current** entitlement and records an `annual_refresh` grant.
 */
class EnsurePtoYear
{
    public function __construct(private readonly PtoTenure $tenure) {}

    public function handle(Person $person, ?CarbonImmutable $asOf = null): PtoYearAllotment
    {
        $asOf ??= CarbonImmutable::now()->startOfDay();
        $start = $this->tenure->anniversaryStart($person, $asOf);

        $existing = PtoYearAllotment::query()
            ->where('person_id', $person->id)
            ->whereDate('year_start', $start->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $hours = $this->tenure->tierAsOf($person, $asOf)->hoursPerBucket();

        $allotment = PtoYearAllotment::create([
            'person_id' => $person->id,
            'year_start' => $start->toDateString(),
            'year_end' => $start->addYear()->toDateString(),
            'tier_at_year_start' => $this->tenure->tierAsOf($person, $start)->value,
            'vacation_allotment' => $hours,
            'scheduled_allotment' => $hours,
            'unscheduled_allotment' => $hours,
            'status' => PtoAllotmentStatus::Open,
        ]);

        PtoGrant::create([
            'person_id' => $person->id,
            'year_allotment_id' => $allotment->id,
            'grant_type' => PtoGrantType::AnnualRefresh,
            'vacation_hours' => $hours,
            'scheduled_hours' => $hours,
            'unscheduled_hours' => $hours,
            'reason' => 'Annual PTO refresh',
            'effective_date' => $start->toDateString(),
        ]);

        return $allotment;
    }
}
