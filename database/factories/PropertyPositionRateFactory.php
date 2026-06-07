<?php

namespace Database\Factories;

use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyPositionRate>
 */
class PropertyPositionRateFactory extends Factory
{
    protected $model = PropertyPositionRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pay = fake()->numberBetween(1500, 3000);   // cents
        $bill = $pay + fake()->numberBetween(800, 1800);

        return [
            'property_id' => Property::factory(),
            'position_id' => Position::factory(),
            'pay_rate' => $pay,
            'bill_rate' => $bill,
            'ot_pay_rate' => (int) round($pay * 1.5),
            'ot_bill_rate' => (int) round($bill * 1.5),
            'effective_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
