<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->setTime(9, 0);
        $end = $start->copy()->addHours(8);

        return [
            'person_id' => Person::factory(),
            'work_order_id' => WorkOrder::factory(),
            'property_id' => Property::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'source' => TimeEntrySource::ManualEntry,
            'clock_method' => 'manual',
            'entry_type' => TimeEntryType::Work,
            'start_at_utc' => $start,
            'end_at_utc' => $end,
            'duration_minutes' => 480,
            'timezone' => 'America/Phoenix',
            'pay_rate_snapshot' => 2000,
            'bill_rate_snapshot' => 3200,
            'ot_pay_rate_snapshot' => 3000,
            'ot_bill_rate_snapshot' => 4800,
            'was_updated' => false,
        ];
    }
}
