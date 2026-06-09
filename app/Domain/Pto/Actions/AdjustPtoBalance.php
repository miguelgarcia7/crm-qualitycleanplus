<?php

namespace App\Domain\Pto\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoGrantType;
use App\Domain\Pto\Models\PtoGrant;

/**
 * Manual PTO balance adjustment by admin (ADR-0016) — applies a per-bucket delta to
 * the person's current year allotment and records a `manual_adjustment` grant for audit.
 *
 * @phpstan-type AdjustData array{vacation_hours?:float, scheduled_hours?:float, unscheduled_hours?:float, reason:string}
 */
class AdjustPtoBalance
{
    public function __construct(private readonly EnsurePtoYear $ensureYear) {}

    /**
     * @param  AdjustData  $data
     */
    public function handle(Person $person, array $data, Person $actor): PtoGrant
    {
        $allotment = $this->ensureYear->handle($person);

        $vacation = (float) ($data['vacation_hours'] ?? 0);
        $scheduled = (float) ($data['scheduled_hours'] ?? 0);
        $unscheduled = (float) ($data['unscheduled_hours'] ?? 0);

        $allotment->update([
            'vacation_allotment' => $allotment->allotmentFor(PtoBucket::Vacation) + $vacation,
            'scheduled_allotment' => $allotment->allotmentFor(PtoBucket::Scheduled) + $scheduled,
            'unscheduled_allotment' => $allotment->allotmentFor(PtoBucket::Unscheduled) + $unscheduled,
        ]);

        return PtoGrant::create([
            'person_id' => $person->id,
            'year_allotment_id' => $allotment->id,
            'grant_type' => PtoGrantType::ManualAdjustment,
            'vacation_hours' => $vacation,
            'scheduled_hours' => $scheduled,
            'unscheduled_hours' => $unscheduled,
            'reason' => $data['reason'],
            'effective_date' => now()->toDateString(),
            'created_by' => $actor->id,
        ]);
    }
}
