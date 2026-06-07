<?php

namespace App\Domain\Time\Jobs;

use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recomputes the (work_order, week) rollup from raw time entries — the bucketing
 * algorithm in 20-domain/time-tracking.md. Called after any time-entry change.
 *
 * v1 simplifications: holiday bucket = 0 (no calendar until Phase 08); training
 * is paid at the WO's pay rate and is not billed; amounts use the WO's rates
 * (entries on a WO share the same snapshot in the core pipeline).
 */
class RecomputeTimeSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $workOrderId,
        public int $payrollPeriodId,
    ) {}

    public function handle(): void
    {
        $workOrder = WorkOrder::find($this->workOrderId);
        $period = PayrollPeriod::find($this->payrollPeriodId);

        if ($workOrder === null || $period === null) {
            return;
        }

        $entries = TimeEntry::query()
            ->where('work_order_id', $this->workOrderId)
            ->where('payroll_period_id', $this->payrollPeriodId)
            ->get();

        $threshold = (int) config('qcp.time.overtime_weekly_threshold_minutes', 2400);
        $tz = $workOrder->property->timezone;

        // Split training out; group work minutes by local day.
        $trainingMinutes = 0;
        $workByDay = [];
        foreach ($entries as $entry) {
            $minutes = $entry->duration_minutes ?? 0;
            if ($entry->entry_type === TimeEntryType::Training) {
                $trainingMinutes += $minutes;

                continue;
            }
            $day = $entry->start_at_utc?->copy()->setTimezone($tz)->toDateString()
                ?? $period->week_start->toDateString();
            $workByDay[$day] = ($workByDay[$day] ?? 0) + $minutes;
        }
        ksort($workByDay);

        // Weekly 40h split: fill regular up to the threshold, remainder is OT.
        $regular = 0;
        $overtime = 0;
        $running = 0;
        foreach ($workByDay as $minutes) {
            $room = max(0, $threshold - $running);
            $reg = min($minutes, $room);
            $regular += $reg;
            $overtime += $minutes - $reg;
            $running += $minutes;
        }

        TimeSummary::updateOrCreate(
            ['work_order_id' => $this->workOrderId, 'week_start' => $period->week_start->toDateString()],
            [
                'person_id' => $workOrder->person_id,
                'property_id' => $workOrder->property_id,
                'payroll_period_id' => $period->id,
                'week_end' => $period->week_end->toDateString(),

                'regular_minutes' => $regular,
                'overtime_minutes' => $overtime,
                'holiday_minutes' => 0,
                'training_minutes' => $trainingMinutes,

                'regular_amount_pay' => $this->amount($regular, $workOrder->pay_rate),
                'overtime_amount_pay' => $this->amount($overtime, $workOrder->ot_pay_rate),
                'holiday_amount_pay' => 0,
                'training_amount_pay' => $this->amount($trainingMinutes, $workOrder->pay_rate),

                'regular_amount_bill' => $this->amount($regular, $workOrder->bill_rate),
                'overtime_amount_bill' => $this->amount($overtime, $workOrder->ot_bill_rate),
                'holiday_amount_bill' => 0,
                'training_amount_bill' => 0,

                'total_pay' => $this->amount($regular, $workOrder->pay_rate)
                    + $this->amount($overtime, $workOrder->ot_pay_rate)
                    + $this->amount($trainingMinutes, $workOrder->pay_rate),
                'total_bill' => $this->amount($regular, $workOrder->bill_rate)
                    + $this->amount($overtime, $workOrder->ot_bill_rate),

                'last_recomputed_at' => now(),
            ],
        );
    }

    /** minutes × rate(cents/hour) → cents. */
    private function amount(int $minutes, int $rateCents): int
    {
        return (int) round($minutes / 60 * $rateCents);
    }
}
