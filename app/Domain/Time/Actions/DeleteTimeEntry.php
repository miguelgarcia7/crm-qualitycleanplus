<?php

namespace App\Domain\Time\Actions;

use App\Domain\Time\Concerns\LogsPayrollActivity;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Validation\ValidationException;

class DeleteTimeEntry
{
    use LogsPayrollActivity;

    public function handle(TimeEntry $entry): void
    {
        if (! $entry->payrollPeriod->status->isEditable()) {
            throw ValidationException::withMessages(['entry' => 'That week is locked; entries can no longer be edited.']);
        }

        $workOrderId = $entry->work_order_id;
        $periodId = $entry->payroll_period_id;

        // Captured before the delete — afterwards the row is gone.
        $tz = $entry->timezone ?? config('app.timezone');
        $start = $entry->start_at_utc?->copy()->setTimezone($tz);
        $end = $entry->end_at_utc?->copy()->setTimezone($tz);
        $person = $entry->person;
        $context = [
            'time_entry_id' => $entry->id,
            'work_order_id' => $workOrderId,
            'property_id' => $entry->property_id,
            'date' => $start?->toDateString(),
            'minutes' => $entry->duration_minutes,
        ];
        $window = $start === null
            ? 'a punch'
            : sprintf('the %s–%s punch on %s', $start->format('g:i a'), $end?->format('g:i a') ?? 'open', $start->format('D j M Y'));

        $entry->delete();

        $this->logPayroll($person, 'deleted', "Removed {$window}", $context);

        RecomputeTimeSummary::dispatchSync($workOrderId, $periodId);
    }
}
