<?php

namespace Database\Factories;

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    protected $model = PayrollPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);

        return [
            'property_id' => Property::factory(),
            'week_start' => $start->toDateString(),
            'week_end' => $start->copy()->addDays(6)->toDateString(),
            'status' => PayrollPeriodStatus::Open,
        ];
    }

    public function forWeek(Carbon $monday): static
    {
        return $this->state(fn (array $attributes) => [
            'week_start' => $monday->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'week_end' => $monday->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)->toDateString(),
        ]);
    }
}
