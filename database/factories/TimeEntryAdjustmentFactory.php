<?php

namespace Database\Factories;

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntryAdjustment>
 */
class TimeEntryAdjustmentFactory extends Factory
{
    protected $model = TimeEntryAdjustment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'work_order_id' => null,
            'property_id' => Property::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'adjustment_item_id' => null,
            'source_type' => AdjustmentSourceType::Manual,
            'source_id' => null,
            'value' => fake()->numberBetween(500, 5000),
            'type' => AdjustmentType::Deduction,
            'is_billable' => false,
        ];
    }
}
