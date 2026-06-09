<?php

namespace App\Domain\Pto\Actions;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoAllotmentStatus;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoGrantType;
use App\Domain\Pto\Models\PtoGrant;
use App\Domain\Pto\Models\PtoYearAllotment;
use App\Domain\Pto\Services\PtoTenure;
use Carbon\CarbonImmutable;

/**
 * Daily PTO accrual engine (ADR-0016). For each active W-2 person: on the hire
 * anniversary, forfeit the prior year and open a fresh one at the current tier; on a
 * tier-crossing day (30d / 6mo / 12mo), top up the current allotment by the delta.
 * Idempotent — safe to rerun for the same date.
 *
 * @phpstan-type Stats array{processed:int, anniversaries:int, crossings:int}
 */
class ProcessPtoTenureCrossings
{
    public function __construct(
        private readonly PtoTenure $tenure,
        private readonly EnsurePtoYear $ensureYear,
    ) {}

    /**
     * @return Stats
     */
    public function handle(?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now()->startOfDay();
        $anniversaries = 0;
        $crossings = 0;

        $people = Person::query()
            ->where('status', PersonStatus::StaffActive->value)
            ->whereNotNull('hire_date')->get();

        foreach ($people as $person) {
            if ($this->closePriorYearOnAnniversary($person, $date)) {
                $anniversaries++;
            }

            $allotment = $this->ensureYear->handle($person, $date);

            if (! $allotment->wasRecentlyCreated && $this->applyTierCrossing($person, $allotment, $date)) {
                $crossings++;
            }
        }

        return ['processed' => $people->count(), 'anniversaries' => $anniversaries, 'crossings' => $crossings];
    }

    private function closePriorYearOnAnniversary(Person $person, CarbonImmutable $date): bool
    {
        if (! $date->isSameDay($this->tenure->anniversaryStart($person, $date))) {
            return false;
        }

        $prior = PtoYearAllotment::query()
            ->where('person_id', $person->id)
            ->where('status', PtoAllotmentStatus::Open->value)
            ->whereDate('year_start', $date->subYear()->toDateString())
            ->first();

        if ($prior === null) {
            return false;
        }

        $prior->update([
            'status' => PtoAllotmentStatus::Forfeited,
            'closed_at' => now(),
            'forfeited_hours_at_close' => [
                'vacation' => $prior->availableFor(PtoBucket::Vacation),
                'scheduled' => $prior->availableFor(PtoBucket::Scheduled),
                'unscheduled' => $prior->availableFor(PtoBucket::Unscheduled),
            ],
        ]);

        return true;
    }

    private function applyTierCrossing(Person $person, PtoYearAllotment $allotment, CarbonImmutable $date): bool
    {
        $today = $this->tenure->tierAsOf($person, $date);
        $yesterday = $this->tenure->tierAsOf($person, $date->subDay());

        if ($today === $yesterday) {
            return false;
        }

        $delta = $today->hoursPerBucket() - $yesterday->hoursPerBucket();
        if ($delta <= 0) {
            return false;
        }

        // Idempotency: only one tier_milestone per allotment per day.
        $already = PtoGrant::query()
            ->where('year_allotment_id', $allotment->id)
            ->where('grant_type', PtoGrantType::TierMilestone->value)
            ->whereDate('effective_date', $date->toDateString())
            ->exists();

        if ($already) {
            return false;
        }

        $allotment->update([
            'vacation_allotment' => $allotment->allotmentFor(PtoBucket::Vacation) + $delta,
            'scheduled_allotment' => $allotment->allotmentFor(PtoBucket::Scheduled) + $delta,
            'unscheduled_allotment' => $allotment->allotmentFor(PtoBucket::Unscheduled) + $delta,
        ]);

        PtoGrant::create([
            'person_id' => $person->id,
            'year_allotment_id' => $allotment->id,
            'grant_type' => PtoGrantType::TierMilestone,
            'vacation_hours' => $delta,
            'scheduled_hours' => $delta,
            'unscheduled_hours' => $delta,
            'reason' => "Tenure tier crossing → {$today->label()}",
            'effective_date' => $date->toDateString(),
        ]);

        return true;
    }
}
