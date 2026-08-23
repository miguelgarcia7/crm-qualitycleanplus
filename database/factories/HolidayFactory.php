<?php

namespace Database\Factories;

use App\Domain\PropertyBible\Enums\HolidayType;
use App\Domain\PropertyBible\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Day';

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name, '_'),
            'type' => HolidayType::Custom,
            'rule' => null,
            'month' => fake()->numberBetween(1, 12),
            'day' => fake()->numberBetween(1, 28),
        ];
    }

    public function legal(string $slug = 'thanksgiving_day'): static
    {
        [$name, $rule] = Holiday::LEGAL_HOLIDAYS[$slug];

        return $this->state([
            'name' => $name,
            'slug' => $slug,
            'type' => HolidayType::Legal,
            'rule' => $rule,
            'month' => null,
            'day' => null,
        ]);
    }
}
