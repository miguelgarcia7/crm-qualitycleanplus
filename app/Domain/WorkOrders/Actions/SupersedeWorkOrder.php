<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Closes a work order and opens its successor at an effective date — the shared
 * "close old, open new" mechanic behind transfers and pay increases (ADR-0019,
 * ADR-0020). The old WO ends the day before the new one starts; the new WO links
 * back via `parent_wo_id` and records its `source`.
 */
class SupersedeWorkOrder
{
    use LogsPropertyActivity;

    public function __construct(private CloseWorkOrder $closeWorkOrder) {}

    /**
     * @param  array{property_id?:int, position_id?:int, person_id?:int, pay_rate:int, bill_rate:int, ot_pay_rate:int, ot_bill_rate:int}  $newData
     */
    public function handle(WorkOrder $old, array $newData, WorkOrderSource $source, CarbonImmutable $effectiveDate, ?Person $actor = null): WorkOrder
    {
        return DB::transaction(function () use ($old, $newData, $source, $effectiveDate, $actor): WorkOrder {
            $this->closeWorkOrder->handle($old, $effectiveDate->subDay()->toDateString());

            $new = WorkOrder::create([
                'person_id' => $newData['person_id'] ?? $old->person_id,
                'property_id' => $newData['property_id'] ?? $old->property_id,
                'position_id' => $newData['position_id'] ?? $old->position_id,
                'pay_rate' => $newData['pay_rate'],
                'bill_rate' => $newData['bill_rate'],
                'ot_pay_rate' => $newData['ot_pay_rate'],
                'ot_bill_rate' => $newData['ot_bill_rate'],
                'start_date' => $effectiveDate->toDateString(),
                'status' => WorkOrderStatus::Active,
                'source' => $source,
                'parent_wo_id' => $old->id,
                'created_by' => $actor?->id,
            ]);

            $new->load('property', 'person', 'position');
            $this->logProperty(
                $new->property,
                'updated',
                "Opened work order #{$new->id} ({$source->value}) superseding #{$old->id}",
            );

            return $new;
        });
    }
}
