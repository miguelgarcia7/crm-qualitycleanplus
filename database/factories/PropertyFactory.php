<?php

namespace Database\Factories;

use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Hotel',
            'pm_name' => fake()->name(),
            'pm_phone' => fake()->phoneNumber(),
            'main_phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['AZ', 'CA', 'NV', 'TX', 'NY', 'FL', 'CO']),
            'zip' => fake()->postcode(),
            'timezone' => 'America/Phoenix',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geofence_radius_meters' => 300,
            'closing_day' => null, // ISO weekday the week ends on; null = Sunday (Mon–Sun week)
            'tax_rate' => 0.0875,
            'status' => PropertyStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Inactive,
        ]);
    }
}
