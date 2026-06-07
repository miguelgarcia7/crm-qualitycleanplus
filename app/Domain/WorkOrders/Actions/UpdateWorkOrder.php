<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\WorkOrders\Models\WorkOrder;

class UpdateWorkOrder
{
    use LogsPropertyActivity;

    /**
     * @param  array<string, mixed>  $data
     *
     * Note: the rate-edit guard (free edits only while the WO has no time
     * entries / the period is open; otherwise use the pay-increase workflow)
     * is enforced once time entries exist — Phase 03 Inc 2 / Phase 04.
     */
    public function handle(WorkOrder $workOrder, array $data): WorkOrder
    {
        $workOrder->update($data);

        $this->logProperty(
            $workOrder->property,
            'updated',
            "Updated work order #{$workOrder->id}",
        );

        return $workOrder;
    }
}
