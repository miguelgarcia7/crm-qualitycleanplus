<?php

namespace App\Domain\People\Actions;

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\People\Models\Person;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use Illuminate\Support\Facades\DB;

/**
 * Consolidate a terminated contractor's outstanding charge schedules into a
 * single capped, non-billable deduction on their open payroll period (ADR-0014,
 * ADR-0018). Open-period-only in v1: with no open period nothing is deducted and
 * the caller surfaces a payroll flag. The deduction is capped at the pay
 * available in the period (cap-at-zero — we never push net pay negative); any
 * uncollected remainder is written off as a QCP expense (activity log).
 *
 * @phpstan-type FinalPaycheckResult array{
 *     no_open_period: bool,
 *     period_id: int|null,
 *     consolidated_cents: int,
 *     remainder_cents: int,
 * }
 */
class ProcessFinalPaycheck
{
    /**
     * @return FinalPaycheckResult
     */
    public function handle(Person $person, ?Person $actor): array
    {
        return DB::transaction(function () use ($person, $actor): array {
            $period = $this->openPeriodFor($person);

            if ($period === null) {
                return ['no_open_period' => true, 'period_id' => null, 'consolidated_cents' => 0, 'remainder_cents' => 0];
            }

            $remaining = $person->outstandingChargeBalance();
            $consolidated = min($remaining, $this->availablePay($person, $period));

            if ($consolidated > 0) {
                $workOrder = $person->workOrders()->latest('id')->first();

                TimeEntryAdjustment::create([
                    'person_id' => $person->id,
                    'work_order_id' => $workOrder?->id,
                    'property_id' => $period->property_id,
                    'payroll_period_id' => $period->id,
                    'source_type' => AdjustmentSourceType::SupplyRequestTerminationConsolidation,
                    'value' => $consolidated,
                    'type' => AdjustmentType::Deduction,
                    'notes' => 'Final paycheck — consolidated outstanding contractor charges',
                    'created_by' => $actor?->id,
                ]);
            }

            $this->accelerateSchedules($person);

            $remainder = max(0, $remaining - $consolidated);
            if ($remainder > 0) {
                activity('inventory')
                    ->causedBy($actor)
                    ->performedOn($person)
                    ->event('charge_written_off')
                    ->withProperties(['remainder_cents' => $remainder])
                    ->log('Uncollected contractor charge written off as QCP expense at termination');
            }

            return [
                'no_open_period' => false,
                'period_id' => $period->id,
                'consolidated_cents' => $consolidated,
                'remainder_cents' => $remainder,
            ];
        });
    }

    /** The contractor's current open payroll period, via their latest work order's property. */
    private function openPeriodFor(Person $person): ?PayrollPeriod
    {
        $workOrder = $person->workOrders()->latest('id')->first();

        if ($workOrder === null) {
            return null;
        }

        return PayrollPeriod::query()
            ->where('property_id', $workOrder->property_id)
            ->where('status', PayrollPeriodStatus::Open)
            ->orderBy('week_start')
            ->first();
    }

    /** Pay available to deduct against: gross pay in the period minus deductions already applied. */
    private function availablePay(Person $person, PayrollPeriod $period): int
    {
        $gross = (int) TimeSummary::query()
            ->where('person_id', $person->id)
            ->where('payroll_period_id', $period->id)
            ->sum('total_pay');

        $deductions = (int) TimeEntryAdjustment::query()
            ->where('person_id', $person->id)
            ->where('payroll_period_id', $period->id)
            ->where('type', AdjustmentType::Deduction->value)
            ->sum('value');

        return max(0, $gross - $deductions);
    }

    /** Close out the contractor's active charge schedules: accelerate them, skip remaining entries. */
    private function accelerateSchedules(Person $person): void
    {
        $schedules = ContractorChargeSchedule::query()
            ->where('person_id', $person->id)
            ->where('status', ChargeScheduleStatus::Active)
            ->get();

        foreach ($schedules as $schedule) {
            $schedule->entries()
                ->where('status', ChargeEntryStatus::Scheduled->value)
                ->update(['status' => ChargeEntryStatus::Skipped->value]);

            $schedule->update(['status' => ChargeScheduleStatus::AcceleratedToFinalPaycheck]);
        }
    }
}
