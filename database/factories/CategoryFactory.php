<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'has_variants' => false,
            'active' => true,
        ];
    }

    public function uniforms(): static
    {
        return $this->state(fn (array $attributes) => ['name' => 'Uniforms', 'slug' => 'uniforms', 'has_variants' => true]);
    }

    public function equipment(): static
    {
        return $this->state(fn (array $attributes) => ['name' => 'Equipment', 'slug' => 'equipment', 'has_variants' => false]);
    }
}
