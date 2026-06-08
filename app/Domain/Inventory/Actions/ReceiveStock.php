<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\People\Models\Person;

/**
 * Receive stock without a purchase order (ADR-0012) — a direct inflow.
 */
class ReceiveStock
{
    public function __construct(private RecordStockMovement $record) {}

    public function handle(ItemVariant $variant, int $quantity, string $reason, ?Person $actor): StockMovement
    {
        return $this->record->handle($variant, MovementType::DirectReceipt, abs($quantity), [
            'reason' => $reason,
        ], $actor);
    }
}
