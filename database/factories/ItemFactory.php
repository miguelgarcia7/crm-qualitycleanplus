<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->words(2, true)),
            'category_id' => Category::factory(),
            'description' => fake()->optional()->sentence(),
            'has_variants' => false,
            'active' => true,
        ];
    }
}
