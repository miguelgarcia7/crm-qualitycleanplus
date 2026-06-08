<?php

namespace App\Domain\WorkOrders\Jobs;

use App\Domain\WorkOrders\Actions\CloseWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Closes temporary-assignment work orders once their fixed window ends (ADR-0019).
 * Scheduled daily; the contractor returns to their home roster automatically.
 */
class ProcessTemporaryAssignmentEnds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CloseWorkOrder $close): void
    {
        $today = CarbonImmutable::now()->toDateString();

        $expired = WorkOrder::query()
            ->where('is_temporary_assignment', true)
            ->where('status', WorkOrderStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', $today)
            ->get();

        foreach ($expired as $workOrder) {
            $close->handle($workOrder, $workOrder->end_date?->toDateString());
        }
    }
}
