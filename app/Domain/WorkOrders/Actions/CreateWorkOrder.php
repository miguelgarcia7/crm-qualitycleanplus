<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Concerns\NormalisesDirectHireThreshold;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Models\WorkOrder;

class CreateWorkOrder
{
    use LogsPropertyActivity, NormalisesDirectHireThreshold;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Person $creator, WorkOrderSource $source = WorkOrderSource::RecruiterCreated): WorkOrder
    {
        // The direct-hire threshold defaults from the property in WorkOrder's
        // saving hook, so imports, temporary assignments and factories get it
        // too — not just this path.
        $workOrder = new WorkOrder($this->normaliseDirectHireThreshold($data, blankMeansInherit: true));
        $workOrder->source = $source;
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
