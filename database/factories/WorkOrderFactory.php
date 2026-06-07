<?php

namespace Database\Factories;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pay = fake()->numberBetween(1500, 3000);
        $bill = $pay + fake()->numberBetween(800, 1800);

        return [
            'person_id' => Person::factory()->state(['status' => PersonStatus::ContractorActive]),
            'property_id' => Property::factory(),
            'position_id' => Position::factory(),
            'pay_rate' => $pay,
            'bill_rate' => $bill,
            'ot_pay_rate' => (int) round($pay * 1.5),
            'ot_bill_rate' => (int) round($bill * 1.5),
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => null,
            'status' => WorkOrderStatus::Active,
            'source' => WorkOrderSource::RecruiterCreated,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkOrderStatus::Closed,
            'end_date' => now()->toDateString(),
        ]);
    }
}
