<?php

namespace App\Domain\WorkOrders\Support;

use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Progress toward direct-hire eligibility: how much of the contracted threshold
 * a contractor has worked at the work order's property.
 *
 * The hours are the person's at that property across all of their work orders
 * there, not just this one: a pay raise closes the work order and opens a new
 * one, and the hours before it still count. The threshold is this work order's.
 *
 * The threshold is a commercial term — the hours a property must wait before it
 * may hire a QCP contractor directly. It counts WORKED time from the weekly
 * summaries, not elapsed calendar time, so a contractor on two shifts a week
 * takes proportionally longer to become eligible.
 *
 * Training minutes are excluded: they are paid by QCP and never billed, so they
 * are not time worked for the property.
 */
class DirectHireProgress
{
    /**
     * @return array<string, mixed>
     */
    public function for(WorkOrder $workOrder): array
    {
        return $this->build(
            (int) $workOrder->direct_hire_threshold_minutes,
            $this->workedMinutes($workOrder),
        );
    }

    /**
     * Progress for many work orders in one query — for list screens.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return array<int, array<string, mixed>> keyed by work order id
     */
    public function forMany(Collection $workOrders): array
    {
        if ($workOrders->isEmpty()) {
            return [];
        }

        // One query: minutes per person and property, across every work order there.
        $worked = TimeSummary::query()
            ->join('work_orders', 'work_orders.id', '=', 'time_summaries.work_order_id')
            ->whereIn('work_orders.person_id', $workOrders->pluck('person_id')->unique())
            ->whereIn('work_orders.property_id', $workOrders->pluck('property_id')->unique())
            ->groupBy('work_orders.person_id', 'work_orders.property_id')
            ->selectRaw('work_orders.person_id, work_orders.property_id, SUM(regular_minutes + overtime_minutes + holiday_minutes) as minutes')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => ["{$row->person_id}:{$row->property_id}" => (int) $row->minutes]);

        $out = [];
        foreach ($workOrders as $workOrder) {
            $out[$workOrder->id] = $this->build(
                (int) $workOrder->direct_hire_threshold_minutes,
                (int) ($worked["{$workOrder->person_id}:{$workOrder->property_id}"] ?? 0),
            );
        }

        return $out;
    }

    private function workedMinutes(WorkOrder $workOrder): int
    {
        return (int) TimeSummary::query()
            ->join('work_orders', 'work_orders.id', '=', 'time_summaries.work_order_id')
            ->where('work_orders.person_id', $workOrder->person_id)
            ->where('work_orders.property_id', $workOrder->property_id)
            ->sum(DB::raw('regular_minutes + overtime_minutes + holiday_minutes'));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $thresholdMinutes, int $workedMinutes): array
    {
        // A zero threshold means "no restriction" rather than "instantly
        // eligible" — treat it as eligible but say so without a bogus 0/0 bar.
        $eligible = $thresholdMinutes <= 0 || $workedMinutes >= $thresholdMinutes;
        $remaining = max(0, $thresholdMinutes - $workedMinutes);

        return [
            'threshold_hours' => round($thresholdMinutes / 60, 1),
            'worked_hours' => round($workedMinutes / 60, 1),
            'remaining_hours' => round($remaining / 60, 1),
            'percent' => $thresholdMinutes > 0
                ? min(100, (int) round(($workedMinutes / $thresholdMinutes) * 100))
                : 100,
            'eligible' => $eligible,
            'unrestricted' => $thresholdMinutes <= 0,
        ];
    }
}
