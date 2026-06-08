<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\People\Models\Person;

/**
 * Return items to stock (ADR-0015) — a positive inflow, optionally linked to the
 * original outflow movement.
 */
class ReturnToStock
{
    public function __construct(private RecordStockMovement $record) {}

    public function handle(ItemVariant $variant, int $quantity, string $reason, ?int $relatedMovementId, ?Person $actor): StockMovement
    {
        return $this->record->handle($variant, MovementType::Return, abs($quantity), [
            'reason' => $reason,
            'related_movement_id' => $relatedMovementId,
        ], $actor);
    }
}
