<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Contractors who reach their property's direct-hire hours within a few weeks
 * at their recent pace (the average of their last two finished weeks),
 * soonest first. One query for every work order, never one per contractor.
 */
class DirectHireSoon
{
    public const WITHIN_WEEKS = 10;

    public function __construct(private readonly DirectHireProgress $progress) {}

    /**
     * @param  Collection<int, WorkOrder>  $activeWorkOrders  with id, person_id, property_id, direct_hire_threshold_minutes
     * @return array{rows: list<array<string, mixed>>, total: int, withinWeeks: int}
     */
    public function build(Collection $activeWorkOrders, CarbonImmutable $today, int $limit): array
    {
        $progress = $this->progress->forMany($activeWorkOrders);

        $candidates = $activeWorkOrders->filter(fn (WorkOrder $wo): bool => ! $progress[$wo->id]['eligible'] && ! $progress[$wo->id]['unrestricted']);

        $pace = TimeSummary::query()
            ->whereIn('work_order_id', $candidates->pluck('id'))
            ->whereDate('week_end', '<', $today->toDateString())
            ->whereDate('week_start', '>=', $today->subWeeks(5)->toDateString())
            ->orderByDesc('week_start')
            ->get(['work_order_id', 'week_start', 'regular_minutes', 'overtime_minutes', 'holiday_minutes'])
            ->groupBy('work_order_id')
            ->map(fn (Collection $weeks): float => $weeks->take(2)->avg(fn (TimeSummary $s): int => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes) / 60);

        $rows = $candidates
            ->map(function (WorkOrder $wo) use ($progress, $pace): ?array {
                $hours = (float) ($pace[$wo->id] ?? 0);
                if ($hours < 1) {
                    return null;
                }

                $weeks = (int) ceil($progress[$wo->id]['remaining_hours'] / $hours);

                return $weeks <= self::WITHIN_WEEKS ? [
                    'workOrderId' => $wo->id,
                    'personId' => $wo->person_id,
                    'propertyId' => $wo->property_id,
                    'worked' => $progress[$wo->id]['worked_hours'],
                    'threshold' => $progress[$wo->id]['threshold_hours'],
                    'weeks' => $weeks,
                ] : null;
            })
            ->filter()
            ->sortBy('weeks')
            ->values();

        $shown = $rows->take($limit);
        $names = Person::query()->whereIn('id', $shown->pluck('personId'))->pluck('name', 'id');
        $properties = Property::withTrashed()->whereIn('id', $shown->pluck('propertyId'))->pluck('name', 'id');

        return [
            'rows' => $shown->map(fn (array $row): array => [
                'id' => $row['workOrderId'],
                'name' => $names[$row['personId']] ?? null,
                'property' => $properties[$row['propertyId']] ?? null,
                'worked' => $row['worked'],
                'threshold' => $row['threshold'],
                'weeks' => $row['weeks'],
                'href' => "/admin/people/{$row['personId']}",
            ])->values()->all(),
            'total' => $rows->count(),
            'withinWeeks' => self::WITHIN_WEEKS,
        ];
    }
}
