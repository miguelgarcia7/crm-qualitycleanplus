<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Creates a purchase order (draft) with its line items (ADR-0012).
 */
class CreatePurchaseOrder
{
    /**
     * @param  array{notes?:string|null, source_request_id?:int|null, items:list<array{item_variant_id:int, quantity:int, estimated_unit_cost?:int|null, notes?:string|null}>}  $data
     */
    public function handle(array $data, ?Person $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor): PurchaseOrder {
            $po = PurchaseOrder::create([
                'status' => PurchaseOrderStatus::Draft,
                'created_by' => $actor?->id,
                'notes' => $data['notes'] ?? null,
                'source_request_id' => $data['source_request_id'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $po->items()->create([
                    'item_variant_id' => $line['item_variant_id'],
                    'quantity' => $line['quantity'],
                    'estimated_unit_cost' => $line['estimated_unit_cost'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $po->load('items');
        });
    }
}
