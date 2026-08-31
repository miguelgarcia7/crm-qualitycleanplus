<?php

namespace Database\Factories;

use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractorChargeSchedule>
 */
class ContractorChargeScheduleFactory extends Factory
{
    protected $model = ContractorChargeSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'total_amount' => 4000,
            'num_payments' => 2,
            'amount_per_payment' => 2000,
            'status' => ChargeScheduleStatus::Active,
        ];
    }
}
