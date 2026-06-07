<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Models\WorkOrder;

class CreateWorkOrder
{
    use LogsPropertyActivity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Person $creator): WorkOrder
    {
        $workOrder = new WorkOrder($data);
        $workOrder->source = WorkOrderSource::RecruiterCreated;
        $workOrder->created_by = $creator?->id;
        $workOrder->save();

        $workOrder->load('person', 'position', 'property');
        $this->logProperty(
            $workOrder->property,
            'updated',
            "Created work order for {$workOrder->person?->name} ({$workOrder->position?->name})",
        );

        return $workOrder;
    }
}
