<?php

namespace App\Domain\Time\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Time\Concerns\LogsPayrollActivity;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Records a recruiter-entered punch on a work order. Resolves the property's
 * payroll period for the date (must exist and be open), snapshots the WO's
 * rates, then recomputes the week's summary.
 */
class CreateManualTimeEntry
{
    use LogsPayrollActivity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(WorkOrder $workOrder, array $data, ?Person $creator): TimeEntry
    {
        $tz = $workOrder->property->timezone;

        $start = CarbonImmutable::parse("{$data['date']} {$data['start_time']}", $tz);
        $end = CarbonImmutable::parse("{$data['date']} {$data['end_time']}", $tz);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay(); // spans midnight
        }
        $duration = (int) $start->diffInMinutes($end);

        $period = $this->openPeriodForDate($workOrder, (string) $data['date'], $tz);

        $entry = TimeEntry::create([
            'person_id' => $workOrder->person_id,
            'work_order_id' => $workOrder->id,
            'property_id' => $workOrder->property_id,
            'payroll_period_id' => $period->id,
            'source' => TimeEntrySource::ManualEntry,
            'clock_method' => 'manual',
            'entry_type' => $data['entry_type'] ?? TimeEntryType::Work->value,
            'start_at_utc' => $start->utc(),
            'end_at_utc' => $end->utc(),
            'duration_minutes' => $duration,
            'timezone' => $tz,
            'pay_rate_snapshot' => $workOrder->pay_rate,
            'bill_rate_snapshot' => $workOrder->bill_rate,
            'ot_pay_rate_snapshot' => $workOrder->ot_pay_rate,
            'ot_bill_rate_snapshot' => $workOrder->ot_bill_rate,
            'created_by' => $creator?->id,
            // A manual entry was never the contractor's own record — source says
            // that. was_updated only marks a punch that was later corrected.
            'was_updated' => false,
        ]);

        RecomputeTimeSummary::dispatchSync($workOrder->id, $period->id);

        $this->logPayroll(
            $workOrder->person,
            'created',
            sprintf(
                'Added a %s–%s punch on %s at %s',
                $start->format('g:i a'),
                $end->format('g:i a'),
                $start->format('D j M Y'),
                $workOrder->property->name,
            ),
            [
                'time_entry_id' => $entry->id,
                'work_order_id' => $workOrder->id,
                'property_id' => $workOrder->property_id,
                'date' => $start->toDateString(),
                'minutes' => $duration,
                'entry_type' => $entry->entry_type->value,
            ],
            $creator,
        );

        return $entry;
    }

    private function openPeriodForDate(WorkOrder $workOrder, string $date, string $tz): PayrollPeriod
    {
        $weekStart = $workOrder->property->weekStartFor($date)->toDateString();

        $period = PayrollPeriod::query()
            ->where('property_id', $workOrder->property_id)
            ->whereDate('week_start', $weekStart)
            ->first();

        if ($period === null) {
            throw ValidationException::withMessages(['date' => 'No payroll period exists for that week yet.']);
        }

        if (! $period->status->isEditable()) {
            throw ValidationException::withMessages(['date' => 'That week is locked; entries can no longer be edited.']);
        }

        return $period;
    }
}
