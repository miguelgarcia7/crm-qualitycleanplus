<?php

namespace App\Console\Commands;

use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Materializes payroll periods ahead of time (ADR-0009) so manual entry always
 * has a period to attach to. Idempotent: one row per (property, week_start).
 * Weeks are Monday–Sunday in the property's timezone.
 */
class EnsurePayrollPeriods extends Command
{
    protected $signature = 'payroll:ensure-periods';

    protected $description = 'Create the current + upcoming open payroll periods for each active property';

    public function handle(): int
    {
        $ahead = (int) config('qcp.time.payroll_periods_ahead', 4);
        $created = 0;

        Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->each(function (Property $property) use ($ahead, &$created): void {
                $monday = Carbon::now($property->timezone)->startOfWeek(Carbon::MONDAY)->startOfDay();

                for ($i = -1; $i <= $ahead; $i++) {
                    $weekStart = $monday->copy()->addWeeks($i);

                    $period = $property->payrollPeriods()->firstOrCreate(
                        ['week_start' => $weekStart->toDateString()],
                        [
                            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
                            'status' => PayrollPeriodStatus::Open,
                        ],
                    );

                    if ($period->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        $this->info("Ensured payroll periods. {$created} created.");

        return self::SUCCESS;
    }
}
