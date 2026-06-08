<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemVariant>
 */
class ItemVariantFactory extends Factory
{
    protected $model = ItemVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'size' => null,
            'color' => null,
            'sku' => null,
            'current_stock' => 0,
            'reorder_threshold' => 0,
            'active' => true,
        ];
    }
}
