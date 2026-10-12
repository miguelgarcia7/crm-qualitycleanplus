<?php

namespace App\Domain\Time\Support;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The rows of a property's weekly grid, shared by the back office grid and the
 * property manager's timesheet.
 */
class WeekWorkOrders
{
    /**
     * Active work orders running during the week, plus any work order with
     * time, a summary or an adjustment in it (one closed mid-week still has
     * hours on the invoice). The date bounds keep a pay increase's new work
     * order out of the weeks before it starts, beside the one it replaced.
     *
     * @param  list<string>  $with
     * @return Collection<int, WorkOrder>
     */
    public static function for(Property $property, CarbonImmutable $weekStart, ?PayrollPeriod $period, array $with = []): Collection
    {
        return $property->workOrders()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('status', WorkOrderStatus::Active->value)
                    ->whereDate('start_date', '<=', $weekStart->addDays(6)->toDateString())
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $weekStart->toDateString())))
                ->when($period !== null, fn ($q) => $q
                    ->orWhereIn('id', TimeEntry::query()->where('payroll_period_id', $period?->id)->select('work_order_id'))
                    ->orWhereIn('id', TimeSummary::query()->where('payroll_period_id', $period?->id)->select('work_order_id'))
                    ->orWhereIn('id', TimeEntryAdjustment::query()->where('payroll_period_id', $period?->id)->whereNotNull('work_order_id')->select('work_order_id'))))
            ->with(['person:id,name', 'position:id,name', ...$with])
            ->get()
            // By contractor, so a new work order doesn't move anyone down the grid.
            ->sortBy(fn (WorkOrder $wo): array => [mb_strtolower($wo->person->name ?? ''), $wo->start_date->toDateString()])
            ->values();
    }
}
