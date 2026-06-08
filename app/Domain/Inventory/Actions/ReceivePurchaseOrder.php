<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receives a purchase order in full (ADR-0012; no partial receipts in v1):
 * records a `purchase_order_receipt` inflow for each line and marks the PO
 * received.
 */
class ReceivePurchaseOrder
{
    public function __construct(private RecordStockMovement $record) {}

    public function handle(PurchaseOrder $po, ?Person $actor): PurchaseOrder
    {
        if (! $po->status->canReceive()) {
            throw ValidationException::withMessages([
                'status' => 'This purchase order can no longer be received.',
            ]);
        }

        return DB::transaction(function () use ($po, $actor): PurchaseOrder {
            foreach ($po->items as $line) {
                $this->record->handle($line->itemVariant, MovementType::PurchaseOrderReceipt, abs($line->quantity), [
                    'reason' => "PO #{$po->id} received",
                    'related_purchase_order_id' => $po->id,
                ], $actor);
            }

            $po->update([
                'status' => PurchaseOrderStatus::Received,
                'received_at' => now(),
                'received_by' => $actor?->id,
            ]);

            return $po->refresh();
        });
    }
}
