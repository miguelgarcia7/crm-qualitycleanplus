<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single chokepoint for changing stock (ADR-0012). Locks the variant row,
 * applies a signed delta, prevents negative stock on outflows, and records the
 * ledger entry — all in one transaction so concurrent issuances can't oversell.
 */
class RecordStockMovement
{
    /**
     * @param  int  $quantity  signed delta (positive in, negative out)
     * @param  array{reason?:string|null, related_purchase_order_id?:int|null, related_request_id?:int|null, related_movement_id?:int|null, recipient_person_id?:int|null, recipient_type?:string|null}  $meta
     */
    public function handle(ItemVariant $variant, MovementType $type, int $quantity, array $meta = [], ?Person $actor = null): StockMovement
    {
        $this->assertSign($type, $quantity);

        return DB::transaction(function () use ($variant, $type, $quantity, $meta, $actor): StockMovement {
            $locked = ItemVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();

            $newStock = $locked->current_stock + $quantity;
            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Not enough stock: {$locked->current_stock} on hand, tried to remove ".abs($quantity).'.',
                ]);
            }

            $movement = StockMovement::create([
                'item_variant_id' => $locked->id,
                'movement_type' => $type,
                'quantity' => $quantity,
                'reason' => $meta['reason'] ?? null,
                'related_purchase_order_id' => $meta['related_purchase_order_id'] ?? null,
                'related_request_id' => $meta['related_request_id'] ?? null,
                'related_movement_id' => $meta['related_movement_id'] ?? null,
                'recipient_person_id' => $meta['recipient_person_id'] ?? null,
                'recipient_type' => $meta['recipient_type'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $locked->update(['current_stock' => $newStock]);

            return $movement;
        });
    }

    private function assertSign(MovementType $type, int $quantity): void
    {
        if ($quantity === 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity cannot be zero.']);
        }

        if ($type->isInflow() && $quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Inflow movements must be positive.']);
        }

        if ($type->isOutflow() && $quantity > 0) {
            throw ValidationException::withMessages(['quantity' => 'Outflow movements must be negative.']);
        }
    }
}
