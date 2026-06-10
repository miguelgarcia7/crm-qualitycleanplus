<?php

namespace Database\Factories;

use App\Domain\KnowledgeBase\Models\KbTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbTag>
 */
class KbTagFactory extends Factory
{
    protected $model = KbTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
