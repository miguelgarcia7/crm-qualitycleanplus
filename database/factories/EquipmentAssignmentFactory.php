<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\EquipmentAssignmentStatus;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentAssignment>
 */
class EquipmentAssignmentFactory extends Factory
{
    protected $model = EquipmentAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_variant_id' => ItemVariant::factory(),
            'assigned_to_person_id' => Person::factory(),
            'quantity' => 1,
            'assigned_at' => now(),
            'status' => EquipmentAssignmentStatus::Assigned,
        ];
    }
}
