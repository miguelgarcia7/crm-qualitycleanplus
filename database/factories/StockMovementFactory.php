<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_variant_id' => ItemVariant::factory(),
            'movement_type' => MovementType::DirectReceipt,
            'quantity' => fake()->numberBetween(1, 50),
            'reason' => 'Initial stock',
        ];
    }
}
