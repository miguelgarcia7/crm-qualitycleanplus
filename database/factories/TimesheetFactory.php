<?php

namespace Database\Factories;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timesheet>
 */
class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'source' => 'clock_in',
            'status' => TimesheetStatus::Draft,
        ];
    }
}
