<?php

namespace Database\Factories;

use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\AdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdjustmentItem>
 */
class AdjustmentItemFactory extends Factory
{
    protected $model = AdjustmentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Pickup Fee', 'Referral Bonus', 'Late Penalty', 'Performance Incentive']),
            'default_value' => fake()->numberBetween(500, 5000),
            'type' => fake()->randomElement(AdjustmentType::cases()),
            'is_billable' => false,
            'active' => true,
        ];
    }

    public function incentive(): static
    {
        return $this->state(fn (array $attributes) => ['type' => AdjustmentType::Incentive]);
    }

    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => ['type' => AdjustmentType::Deduction, 'is_billable' => false]);
    }
}
