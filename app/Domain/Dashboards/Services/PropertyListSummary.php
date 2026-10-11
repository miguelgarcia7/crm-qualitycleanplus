<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * The extra columns of the properties list: how many contractors are placed
 * at each property and what needs someone there. Weekly figures stay on the
 * property's own Overview — the list is for finding a property. Built for the
 * whole list at once, a handful of queries however many properties there are.
 */
class PropertyListSummary
{
    public function __construct(private readonly PropertyAttention $attention) {}

    /**
     * @param  Collection<int, Property>  $properties
     * @return array<int, array<string, mixed>> keyed by property id
     */
    public function forMany(Collection $properties, Person $user): array
    {
        $contractors = $user->can('work_orders.view') ? $this->contractorCounts($properties) : null;
        $attention = $this->attention->forMany($properties, $user);

        $out = [];
        foreach ($properties as $property) {
            $items = $attention[$property->id] ?? [];

            $out[$property->id] = [
                // Null when the viewer can't see work orders, so the column hides.
                'contractors' => $contractors === null ? null : ($contractors[$property->id] ?? 0),
                'attention_count' => count($items),
                'attention_top' => $items[0]['title'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Contractors with an active work order, per property — people, not work
     * orders, so a pay increase's second work order doesn't count twice.
     *
     * @param  Collection<int, Property>  $properties
     * @return array<int, int>
     */
    private function contractorCounts(Collection $properties): array
    {
        return WorkOrder::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->where('status', WorkOrderStatus::Active->value)
            ->groupBy('property_id')
            ->selectRaw('property_id, COUNT(DISTINCT person_id) as people')
            ->pluck('people', 'property_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }
}
