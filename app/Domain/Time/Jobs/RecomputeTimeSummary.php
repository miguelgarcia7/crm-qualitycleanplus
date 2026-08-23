<?php

namespace App\Domain\Time\Jobs;

use App\Domain\Reports\Actions\RefreshWeeklyRollup;
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
 * Holiday rule: work whose property-local start date is one of the property's
 * attached holidays goes entirely to the holiday bucket (base rate ×
 * qcp.time.holiday_multiplier) and is never overtime — but it still advances
 * the weekly 40h counter, so holiday hours push later days into OT. Attribution
 * is by start date only (an overnight shift starting on the holiday is all
 * holiday). Training is paid at the WO's pay rate and is not billed; amounts
 * use the WO's rates (entries on a WO share the same snapshot).
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
        $property = $workOrder->property;
        $tz = $property->timezone;

        // Holiday dates for every year this week can touch (weeks span New Year).
        $holidayDates = $property->holidayDates([
            $period->week_start->year,
            $period->week_end->year,
        ]);

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

        // Weekly 40h split: holiday days go entirely to the holiday bucket
        // (never OT) but advance the counter; other days fill regular up to
        // the threshold, remainder is OT.
        $regular = 0;
        $overtime = 0;
        $holiday = 0;
        $running = 0;
        foreach ($workByDay as $day => $minutes) {
            if (isset($holidayDates[$day])) {
                $holiday += $minutes;
                $running += $minutes;

                continue;
            }
            $room = max(0, $threshold - $running);
            $reg = min($minutes, $room);
            $regular += $reg;
            $overtime += $minutes - $reg;
            $running += $minutes;
        }

        $multiplier = (float) config('qcp.time.holiday_multiplier', 1.5);
        $holidayPayRate = (int) round($workOrder->pay_rate * $multiplier);
        $holidayBillRate = (int) round($workOrder->bill_rate * $multiplier);

        TimeSummary::updateOrCreate(
            ['work_order_id' => $this->workOrderId, 'payroll_period_id' => $period->id],
            [
                'person_id' => $workOrder->person_id,
                'property_id' => $workOrder->property_id,
                'week_start' => $period->week_start->toDateString(),
                'week_end' => $period->week_end->toDateString(),

                'regular_minutes' => $regular,
                'overtime_minutes' => $overtime,
                'holiday_minutes' => $holiday,
                'training_minutes' => $trainingMinutes,

                'regular_amount_pay' => $this->amount($regular, $workOrder->pay_rate),
                'overtime_amount_pay' => $this->amount($overtime, $workOrder->ot_pay_rate),
                'holiday_amount_pay' => $this->amount($holiday, $holidayPayRate),
                'training_amount_pay' => $this->amount($trainingMinutes, $workOrder->pay_rate),

                'regular_amount_bill' => $this->amount($regular, $workOrder->bill_rate),
                'overtime_amount_bill' => $this->amount($overtime, $workOrder->ot_bill_rate),
                'holiday_amount_bill' => $this->amount($holiday, $holidayBillRate),
                'training_amount_bill' => 0,

                'total_pay' => $this->amount($regular, $workOrder->pay_rate)
                    + $this->amount($overtime, $workOrder->ot_pay_rate)
                    + $this->amount($holiday, $holidayPayRate)
                    + $this->amount($trainingMinutes, $workOrder->pay_rate),
                'total_bill' => $this->amount($regular, $workOrder->bill_rate)
                    + $this->amount($overtime, $workOrder->ot_bill_rate)
                    + $this->amount($holiday, $holidayBillRate),

                'last_recomputed_at' => now(),
            ],
        );

        // Keep the report rollup's weekly cell in step (ADR-0028).
        app(RefreshWeeklyRollup::class)->handle($workOrder->property_id, $period->week_start->toDateString());
    }

    /** minutes × rate(cents/hour) → cents. */
    private function amount(int $minutes, int $rateCents): int
    {
        return (int) round($minutes / 60 * $rateCents);
    }
}
