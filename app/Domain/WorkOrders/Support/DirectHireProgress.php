<?php

namespace App\Domain\WorkOrders\Support;

use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Progress toward direct-hire eligibility: how much of the contracted threshold
 * a contractor has worked on a given work order.
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
            $this->workedMinutes($workOrder->id),
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

        $worked = TimeSummary::query()
            ->whereIn('work_order_id', $workOrders->pluck('id'))
            ->selectRaw('work_order_id, SUM(regular_minutes + overtime_minutes + holiday_minutes) as minutes')
            ->groupBy('work_order_id')
            ->pluck('minutes', 'work_order_id');

        $out = [];
        foreach ($workOrders as $workOrder) {
            $out[$workOrder->id] = $this->build(
                (int) $workOrder->direct_hire_threshold_minutes,
                (int) ($worked[$workOrder->id] ?? 0),
            );
        }

        return $out;
    }

    private function workedMinutes(int $workOrderId): int
    {
        return (int) TimeSummary::query()
            ->where('work_order_id', $workOrderId)
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
