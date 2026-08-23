<?php

namespace App\Domain\Time\Actions;

use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;

/**
 * Re-runs the bucketing job for every (work order, week) that still has an
 * editable payroll period at the given properties. Used when something that
 * feeds the math changes out-of-band — e.g. a property's holiday set. Closed
 * and invoiced weeks are deliberately untouched: their summaries are history
 * and their invoices are frozen (ADR-0006).
 */
class RecomputeOpenPeriods
{
    /**
     * @param  list<int>  $propertyIds
     */
    public function handle(array $propertyIds): void
    {
        if ($propertyIds === []) {
            return;
        }

        $openPeriodIds = PayrollPeriod::query()
            ->whereIn('property_id', $propertyIds)
            ->get()
            ->filter(fn (PayrollPeriod $period): bool => $period->status->isEditable())
            ->pluck('id');

        if ($openPeriodIds->isEmpty()) {
            return;
        }

        TimeEntry::query()
            ->whereIn('payroll_period_id', $openPeriodIds)
            ->distinct()
            ->get(['work_order_id', 'payroll_period_id'])
            ->each(function (TimeEntry $pair): void {
                RecomputeTimeSummary::dispatch($pair->work_order_id, $pair->payroll_period_id);
            });
    }
}
