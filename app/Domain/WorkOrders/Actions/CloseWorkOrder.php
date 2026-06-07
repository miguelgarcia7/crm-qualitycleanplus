<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;

class CloseWorkOrder
{
    use LogsPropertyActivity;

    public function handle(WorkOrder $workOrder, ?string $endDate = null): WorkOrder
    {
        $workOrder->status = WorkOrderStatus::Closed;
        $workOrder->end_date = $endDate !== null ? CarbonImmutable::parse($endDate) : CarbonImmutable::now();
        $workOrder->save();

        $this->logProperty(
            $workOrder->property,
            'updated',
            "Closed work order #{$workOrder->id}",
        );

        return $workOrder;
    }
}
