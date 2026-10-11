<?php

namespace App\Domain\People\Support;

use App\Domain\People\Models\Person;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The contractor profile's Overview tab: where the person works today, how far
 * each placement is toward direct hire, and their latest weeks of hours. A
 * contractor may hold more than one active work order, so everything here is
 * per placement or summed across them.
 */
class PersonOverview
{
    /** Weeks of hours the Overview lists; the Hours tab has the rest. */
    private const RECENT_WEEKS = 4;

    public function __construct(private readonly DirectHireProgress $directHire) {}

    /**
     * Active work orders, oldest first, each with its direct-hire progress and
     * how long the rest takes at the recent pace.
     *
     * @return list<array<string, mixed>>
     */
    public function placements(Person $person, bool $withRates): array
    {
        $workOrders = $person->workOrders()
            ->where('status', WorkOrderStatus::Active)
            ->with(['property:id,name,timezone,closing_day', 'position:id,name'])
            ->orderBy('start_date')
            ->get();

        $progress = $this->directHire->forMany($workOrders);

        return $workOrders->map(function (WorkOrder $wo) use ($progress, $withRates): array {
            $dh = $progress[$wo->id];

            return [
                'id' => $wo->id,
                'property_id' => $wo->property_id,
                'property' => $wo->property?->name,
                'position' => $wo->position?->name,
                'start_date' => $wo->start_date->format('M j, Y'),
                'pay_rate' => $withRates ? $wo->pay_rate : null,
                'bill_rate' => $withRates ? $wo->bill_rate : null,
                'direct_hire' => $dh,
                'weeks_to_go' => $this->weeksToGo($wo, $dh),
            ];
        })->values()->all();
    }

    /**
     * The person's weekly summaries, newest first: one line per week and
     * placement, so each opens the right property's timesheet.
     *
     * @return list<array<string, mixed>>
     */
    public function weeks(Person $person, int $limit): array
    {
        $summaries = TimeSummary::query()
            ->where('person_id', $person->id)
            ->with(['property:id,name,timezone,closing_day', 'workOrder.position:id,name'])
            ->orderByDesc('week_start')
            ->orderBy('property_id')
            ->limit($limit)
            ->get();

        return $summaries->map(fn (TimeSummary $s): array => [
            'id' => $s->id,
            'week_start' => $s->week_start->toDateString(),
            'week_end' => $s->week_end->toDateString(),
            'is_current' => $this->isCurrent($s),
            'property_id' => $s->property_id,
            'property' => $s->property?->name,
            'position' => $s->workOrder?->position?->name,
            'regular_minutes' => $s->regular_minutes,
            'overtime_minutes' => $s->overtime_minutes,
            'holiday_minutes' => $s->holiday_minutes,
            'training_minutes' => $s->training_minutes,
        ])->values()->all();
    }

    /**
     * The latest weeks for the Overview: every line from the newest few weeks.
     *
     * @param  list<array<string, mixed>>  $weeks  from weeks(), newest first
     * @return list<array<string, mixed>>
     */
    public function recentWeeks(array $weeks): array
    {
        $starts = collect($weeks)->pluck('week_start')->unique()->take(self::RECENT_WEEKS)->all();

        return collect($weeks)->whereIn('week_start', $starts)->values()->all();
    }

    /**
     * The newest week that has finished at every property, summed across
     * placements; null when there is none yet.
     *
     * @param  list<array<string, mixed>>  $weeks  from weeks(), newest first
     * @return array{week_start: string, week_end: string, total_minutes: int, overtime_minutes: int}|null
     */
    public function lastFullWeek(array $weeks): ?array
    {
        $finished = collect($weeks)->reject(fn (array $w): bool => $w['is_current']);
        $start = $finished->first()['week_start'] ?? null;

        if ($start === null) {
            return null;
        }

        $week = $finished->where('week_start', $start);

        return [
            'week_start' => $start,
            'week_end' => $week->first()['week_end'],
            'total_minutes' => (int) $week->sum(fn (array $w): int => $w['regular_minutes'] + $w['overtime_minutes'] + $w['holiday_minutes'] + $w['training_minutes']),
            'overtime_minutes' => (int) $week->sum('overtime_minutes'),
        ];
    }

    private function isCurrent(TimeSummary $summary): bool
    {
        $property = $summary->property;

        if ($property === null) {
            return false;
        }

        $thisWeek = $property->weekStartFor(CarbonImmutable::now($property->timezone))->toDateString();

        return $summary->week_start->toDateString() === $thisWeek;
    }

    /**
     * Weeks until the direct-hire threshold at the recent pace: the two latest
     * finished weeks on this work order, so a short first week or the week in
     * progress doesn't skew it. Null when there's no pace to go on, or nothing
     * left to count.
     *
     * @param  array<string, mixed>  $progress
     */
    private function weeksToGo(WorkOrder $workOrder, array $progress): ?int
    {
        if ($progress['unrestricted'] || $progress['eligible']) {
            return null;
        }

        $property = $workOrder->property;
        $thisWeek = $property?->weekStartFor(CarbonImmutable::now($property->timezone))->toDateString();

        /** @var Collection<int, TimeSummary> $finished */
        $finished = TimeSummary::query()
            ->where('work_order_id', $workOrder->id)
            ->when($thisWeek !== null, fn ($q) => $q->whereDate('week_start', '<', $thisWeek))
            ->orderByDesc('week_start')
            ->limit(2)
            ->get();

        if ($finished->isEmpty()) {
            return null;
        }

        $pace = $finished->avg(fn (TimeSummary $s): int => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes) / 60;

        return $pace < 1 ? null : (int) round($progress['remaining_hours'] / $pace);
    }
}
