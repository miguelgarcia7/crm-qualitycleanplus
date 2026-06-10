<?php

namespace Database\Factories;

use App\Domain\KnowledgeBase\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbCategory>
 */
class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
