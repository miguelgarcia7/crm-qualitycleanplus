<?php

namespace App\Domain\Time\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Validation\ValidationException;

/**
 * Records an imported weekly-total time entry (Phase 05, 40-flows/import-hours.md).
 * Unlike a clock punch there are no start/end timestamps — only a duration in
 * minutes. Rates are snapshotted from the work order (ADR-0005) and the week's
 * summary is recomputed. The period must be open.
 *
 * @phpstan-type ImportedEntryData array{duration_minutes:int, payroll_period_id:int, source_metadata?:array<string, mixed>}
 */
class CreateImportedTimeEntry
{
    /**
     * @param  ImportedEntryData  $data
     */
    public function handle(WorkOrder $workOrder, array $data, ?Person $creator): TimeEntry
    {
        $period = PayrollPeriod::findOrFail($data['payroll_period_id']);

        if (! $period->status->isEditable()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'That week is locked; imported hours can no longer be added.',
            ]);
        }

        $entry = TimeEntry::create([
            'person_id' => $workOrder->person_id,
            'work_order_id' => $workOrder->id,
            'property_id' => $workOrder->property_id,
            'payroll_period_id' => $period->id,
            'source' => TimeEntrySource::Imported,
            'clock_method' => null, // imports have no clock event
            'entry_type' => TimeEntryType::Work,
            'start_at_utc' => null,
            'end_at_utc' => null,
            'duration_minutes' => $data['duration_minutes'],
            'timezone' => $workOrder->property->timezone,
            'pay_rate_snapshot' => $workOrder->pay_rate,
            'bill_rate_snapshot' => $workOrder->bill_rate,
            'ot_pay_rate_snapshot' => $workOrder->ot_pay_rate,
            'ot_bill_rate_snapshot' => $workOrder->ot_bill_rate,
            'source_metadata' => $data['source_metadata'] ?? null,
            'created_by' => $creator?->id,
        ]);

        RecomputeTimeSummary::dispatchSync($workOrder->id, $period->id);

        return $entry;
    }
}
