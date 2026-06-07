<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TimeSummary>
 */
class TimeSummaryFactory extends Factory
{
    protected $model = TimeSummary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);

        return [
            'person_id' => Person::factory(),
            'work_order_id' => WorkOrder::factory(),
            'property_id' => Property::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'week_start' => $start->toDateString(),
            'week_end' => $start->copy()->addDays(6)->toDateString(),
        ];
    }
}
