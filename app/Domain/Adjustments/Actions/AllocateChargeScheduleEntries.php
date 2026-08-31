<?php

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Assigns pending payments of a charge schedule to payroll periods that exist
 * now, and is safe to run repeatedly.
 *
 * Schedules longer than the open-period window cannot be allocated in one go:
 * a $250 hiring fee at $20 a period needs 13, and `payroll_periods_ahead` keeps
 * about 4 open. Rather than under-schedule, entries are added as periods appear
 * — so the deduction continues across months without anyone re-entering it.
 *
 * The final payment is whatever remains, so a fee that does not divide evenly
 * (250 / 20) ends with a smaller last payment rather than over-collecting.
 */
class AllocateChargeScheduleEntries
{
    public function handle(ContractorChargeSchedule $schedule): int
    {
        if ($schedule->status !== ChargeScheduleStatus::Active) {
            return 0;
        }

        $allocated = (int) $schedule->entries()->sum('amount');
        $remaining = $schedule->total_amount - $allocated;

        if ($remaining <= 0) {
            return 0;
        }

        $property = $this->propertyFor($schedule);

        if ($property === null) {
            return 0;
        }

        $usedPeriodIds = $schedule->entries()->pluck('payroll_period_id')->all();

        // Open periods only. A locked period has a submitted timesheet, and the
        // apply job processes open periods exclusively — an entry parked on a
        // locked one would never apply while still counting as allocated,
        // stranding that slice of the fee.
        $periods = PayrollPeriod::query()
            ->where('property_id', $property)
            ->whereNotIn('id', $usedPeriodIds)
            ->where('status', PayrollPeriodStatus::Open)
            ->orderBy('week_start')
            ->get();

        if ($periods->isEmpty()) {
            return 0;
        }

        $nextIndex = (int) $schedule->entries()->max('payment_index') + 1;
        $created = 0;

        DB::transaction(function () use ($schedule, $periods, &$remaining, &$nextIndex, &$created): void {
            foreach ($periods as $period) {
                if ($remaining <= 0) {
                    break;
                }

                $amount = min($schedule->amount_per_payment, $remaining);

                $schedule->entries()->create([
                    'payroll_period_id' => $period->id,
                    'amount' => $amount,
                    'payment_index' => $nextIndex,
                    'status' => ChargeEntryStatus::Scheduled,
                ]);

                $remaining -= $amount;
                $nextIndex++;
                $created++;
            }
        });

        return $created;
    }

    /**
     * Charges land on the property the contractor is currently placed at, so a
     * transfer moves later payments with them.
     */
    private function propertyFor(ContractorChargeSchedule $schedule): ?int
    {
        $workOrder = $schedule->person
            ?->workOrders()
            ->where('status', WorkOrderStatus::Active->value)
            ->latest('start_date')
            ->first();

        return $workOrder?->property_id;
    }
}
