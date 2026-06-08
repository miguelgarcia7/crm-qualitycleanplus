<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\EquipmentAssignmentStatus;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Resolve an equipment assignment (ADR-0012, ADR-0018). A returned item flows
 * back into stock; a lost item is written off (no stock movement) and recorded
 * as a QCP expense via the assignment status.
 */
class ReturnEquipment
{
    public function __construct(private RecordStockMovement $record) {}

    public function handle(EquipmentAssignment $assignment, bool $returned, ?string $notes, ?Person $actor): EquipmentAssignment
    {
        return DB::transaction(function () use ($assignment, $returned, $notes, $actor): EquipmentAssignment {
            if ($returned) {
                $this->record->handle($assignment->itemVariant, MovementType::Return, abs($assignment->quantity), [
                    'reason' => 'Equipment returned',
                    'recipient_person_id' => $assignment->assigned_to_person_id,
                ], $actor);
            }

            $assignment->update([
                'status' => $returned ? EquipmentAssignmentStatus::Returned : EquipmentAssignmentStatus::Lost,
                'returned_at' => now(),
                'returned_by' => $actor?->id,
                'return_notes' => $notes,
            ]);

            return $assignment->refresh();
        });
    }
}
