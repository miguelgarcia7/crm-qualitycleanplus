<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\People\Models\Person;

/**
 * Informal stock outflow (ADR-0015): someone takes items without a supply
 * request. No charge, no schedule, no payroll deduction — just a logged movement.
 */
class ManualStockOut
{
    public function __construct(private RecordStockMovement $record) {}

    /**
     * @param  array{recipient_person_id?:int|null, recipient_type?:string|null}  $recipient
     */
    public function handle(ItemVariant $variant, int $quantity, string $reason, array $recipient, ?Person $actor): StockMovement
    {
        return $this->record->handle($variant, MovementType::ManualIssuance, -abs($quantity), [
            'reason' => $reason,
            'recipient_person_id' => $recipient['recipient_person_id'] ?? null,
            'recipient_type' => $recipient['recipient_type'] ?? null,
        ], $actor);
    }
}
