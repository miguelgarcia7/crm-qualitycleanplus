<?php

namespace App\Domain\Time\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Time\Concerns\LogsPayrollActivity;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Corrects the times on an existing punch.
 *
 * Before this, fixing a wrong clock-out meant deleting the entry and adding a
 * replacement — a correct result, but it read in the audit log as two unrelated
 * events. This records one `updated` entry carrying both the old and new times,
 * so the trail says what actually changed.
 *
 * Rate snapshots are deliberately NOT re-taken: the entry keeps the rates that
 * applied when the work was done (ADR-0005). Correcting a time must not
 * silently re-price historical work at today's rates.
 */
class UpdateTimeEntry
{
    use LogsPayrollActivity;

    /**
     * @param  array{start_time: string, end_time: string, entry_type?: string}  $data
     */
    public function handle(TimeEntry $entry, array $data): TimeEntry
    {
        if (! $entry->payrollPeriod->status->isEditable()) {
            throw ValidationException::withMessages(['entry' => 'That week is locked; entries can no longer be edited.']);
        }

        $tz = $entry->timezone ?? $entry->property->timezone;

        // The date is fixed — moving a punch to another day could land it in a
        // different payroll period, which is a delete-and-re-add, not an edit.
        $date = $entry->start_at_utc?->copy()->setTimezone($tz)->toDateString()
            ?? throw ValidationException::withMessages(['entry' => 'This punch has no start time to correct.']);

        $start = CarbonImmutable::parse("{$date} {$data['start_time']}", $tz);
        $end = CarbonImmutable::parse("{$date} {$data['end_time']}", $tz);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay(); // spans midnight
        }

        $before = [
            'start' => $entry->start_at_utc->copy()->setTimezone($tz)->format('g:i a'),
            'end' => $entry->end_at_utc?->copy()->setTimezone($tz)->format('g:i a'),
            'minutes' => $entry->duration_minutes,
        ];

        $editor = auth()->user();

        $entry->update([
            'start_at_utc' => $start->utc(),
            'end_at_utc' => $end->utc(),
            'duration_minutes' => (int) $start->diffInMinutes($end),
            'entry_type' => $data['entry_type'] ?? $entry->entry_type,
            // Marks the punch as no longer purely the contractor's own record.
            // Never cleared once set — it says the row was touched, not who last touched it.
            'was_updated' => $entry->was_updated || ! ($editor instanceof Person && $editor->is($entry->person)),
        ]);

        RecomputeTimeSummary::dispatchSync($entry->work_order_id, $entry->payroll_period_id);

        $this->logPayroll(
            $entry->person,
            'updated',
            sprintf(
                'Changed the %s punch on %s from %s–%s to %s–%s',
                $entry->property->name,
                $start->format('D j M Y'),
                $before['start'],
                $before['end'] ?? 'open',
                $start->format('g:i a'),
                $end->format('g:i a'),
            ),
            [
                'time_entry_id' => $entry->id,
                'work_order_id' => $entry->work_order_id,
                'property_id' => $entry->property_id,
                'date' => $date,
                'from' => $before,
                'to' => ['start' => $start->format('g:i a'), 'end' => $end->format('g:i a'), 'minutes' => $entry->duration_minutes],
            ],
        );

        return $entry;
    }
}
