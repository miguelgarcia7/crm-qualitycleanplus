<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;

/**
 * Opens a fixed-window temporary assignment WO at another property (ADR-0019).
 * The contractor's home WO is left open; this child WO carries a required
 * `end_date` and the `is_temporary_assignment` flag so it's excluded from roster
 * counts and auto-closed at its end date.
 */
class OpenTemporaryWorkOrder
{
    use LogsPropertyActivity;

    /**
     * @param  array{person_id:int, property_id:int, position_id:int, pay_rate:int, bill_rate:int, ot_pay_rate:int, ot_bill_rate:int, start_date:string, end_date:string}  $data
     */
    public function handle(WorkOrder $homeWorkOrder, array $data, ?Person $actor = null): WorkOrder
    {
        $temp = WorkOrder::create([
            'person_id' => $data['person_id'],
            'property_id' => $data['property_id'],
            'position_id' => $data['position_id'],
            'pay_rate' => $data['pay_rate'],
            'bill_rate' => $data['bill_rate'],
            'ot_pay_rate' => $data['ot_pay_rate'],
            'ot_bill_rate' => $data['ot_bill_rate'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => WorkOrderStatus::Active,
            'source' => WorkOrderSource::TemporaryAssignment,
            'is_temporary_assignment' => true,
            'parent_wo_id' => $homeWorkOrder->id,
            'created_by' => $actor?->id,
        ]);

        $temp->load('property', 'person', 'position');
        $this->logProperty(
            $temp->property,
            'updated',
            "Opened temporary work order #{$temp->id} for {$temp->person?->name} (until {$data['end_date']})",
        );

        return $temp;
    }
}
