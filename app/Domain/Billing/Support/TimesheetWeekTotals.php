<?php

namespace App\Domain\Billing\Support;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Time\Models\TimeSummary;
use Illuminate\Support\Collection;

/**
 * A week's billable figures for timesheet lists, in two queries for a whole
 * page of weeks. Bill side only: a property manager never sees pay.
 */
class TimesheetWeekTotals
{
    /**
     * @param  iterable<int>  $periodIds
     * @return Collection<int, array{people: int, total_minutes: int, overtime_minutes: int, holiday_minutes: int, amount: int}>
     */
    public static function forPeriods(iterable $periodIds): Collection
    {
        $ids = collect($periodIds)->unique()->values()->all();

        if ($ids === []) {
            return collect();
        }

        $sums = TimeSummary::query()
            ->join('work_orders', 'work_orders.id', '=', 'time_summaries.work_order_id')
            ->whereIn('time_summaries.payroll_period_id', $ids)
            ->groupBy('time_summaries.payroll_period_id')
            ->selectRaw('time_summaries.payroll_period_id as period_id')
            ->selectRaw('COUNT(DISTINCT work_orders.person_id) as people')
            ->selectRaw('SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as total_minutes')
            ->selectRaw('SUM(overtime_minutes) as overtime_minutes')
            ->selectRaw('SUM(holiday_minutes) as holiday_minutes')
            ->selectRaw('SUM(total_bill) as total_bill')
            ->toBase()
            ->get()
            ->keyBy('period_id');

        // Billable adjustments are on the invoice beside the hours (GenerateInvoice).
        $adjustments = TimeEntryAdjustment::query()
            ->whereIn('payroll_period_id', $ids)
            ->where('is_billable', true)
            ->groupBy('payroll_period_id')
            ->selectRaw('payroll_period_id, SUM(value) as total')
            ->pluck('total', 'payroll_period_id');

        return collect($ids)->mapWithKeys(function (int $id) use ($sums, $adjustments): array {
            $s = $sums->get($id);

            return [$id => [
                'people' => (int) ($s->people ?? 0),
                'total_minutes' => (int) ($s->total_minutes ?? 0),
                'overtime_minutes' => (int) ($s->overtime_minutes ?? 0),
                'holiday_minutes' => (int) ($s->holiday_minutes ?? 0),
                'amount' => (int) ($s->total_bill ?? 0) + (int) ($adjustments[$id] ?? 0),
            ]];
        });
    }
}
