<?php

namespace App\Domain\WorkOrders\Jobs;

use App\Domain\WorkOrders\Actions\CloseWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Support\CompanyTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Closes temporary-assignment work orders once their fixed window ends (ADR-0019).
 * Scheduled daily; the contractor returns to their home roster automatically.
 *
 * The end date is the last day worked, so the assignment closes once that day
 * is over at the property — in the property's own timezone (ADR-0030). Closing
 * on the end date itself would stop the contractor clocking in on their last day.
 */
class ProcessTemporaryAssignmentEnds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CloseWorkOrder $close): void
    {
        $expired = WorkOrder::query()
            ->with('property:id,timezone')
            ->where('is_temporary_assignment', true)
            ->where('status', WorkOrderStatus::Active->value)
            ->whereNotNull('end_date')
            // No property is a whole day ahead of the company: this only narrows the list.
            ->whereDate('end_date', '<=', CompanyTime::now()->toDateString())
            ->get()
            ->filter(fn (WorkOrder $wo): bool => $wo->end_date->toDateString() < ($wo->property?->localDate() ?? CompanyTime::now()->toDateString()));

        foreach ($expired as $workOrder) {
            $close->handle($workOrder, $workOrder->end_date?->toDateString());
        }
    }
}
