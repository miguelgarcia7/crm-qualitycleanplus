<?php

namespace App\Domain\Imports\Support;

use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\PersonExternalId;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;

/**
 * Matches an import row to a contractor and active work order, then classifies it
 * (40-flows/import-hours.md §Step 1). Shared by the initial parse and the resolve
 * step (which re-classifies after a contractor is linked or created).
 */
class ImportRowMatcher
{
    public function matchPerson(int $propertyId, string $externalId): ?Person
    {
        return PersonExternalId::query()
            ->where('property_id', $propertyId)
            ->where('external_id', $externalId)
            ->first()?->person;
    }

    public function activeWorkOrder(int $personId, int $propertyId): ?WorkOrder
    {
        return WorkOrder::query()
            ->where('person_id', $personId)
            ->where('property_id', $propertyId)
            ->where('status', WorkOrderStatus::Active->value)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{status: ImportRowStatus, existing: ?WorkOrder, delta_warning: bool}
     */
    public function classify(?Person $person, int $propertyId, int $payCents, ?int $billCents): array
    {
        if ($person === null) {
            return ['status' => ImportRowStatus::Unmatched, 'existing' => null, 'delta_warning' => false];
        }

        $existing = $this->activeWorkOrder($person->id, $propertyId);

        if ($existing === null) {
            return ['status' => ImportRowStatus::NeedsWoCreation, 'existing' => null, 'delta_warning' => false];
        }

        $status = $this->ratesMatch($existing, $payCents, $billCents)
            ? ImportRowStatus::Matched
            : ImportRowStatus::RateConflict;

        return [
            'status' => $status,
            'existing' => $existing,
            'delta_warning' => $this->largeDelta($existing->pay_rate, $payCents),
        ];
    }

    private function ratesMatch(WorkOrder $wo, int $payCents, ?int $billCents): bool
    {
        if ($wo->pay_rate !== $payCents) {
            return false;
        }

        return $billCents === null || $wo->bill_rate === $billCents;
    }

    private function largeDelta(int $existingCents, int $fileCents): bool
    {
        if ($existingCents === 0) {
            return $fileCents !== 0;
        }

        return abs($fileCents - $existingCents) / $existingCents > 0.5;
    }
}
