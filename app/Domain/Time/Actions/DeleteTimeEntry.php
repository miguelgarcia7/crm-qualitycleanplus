<?php

namespace App\Domain\Time\Actions;

use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Validation\ValidationException;

class DeleteTimeEntry
{
    public function handle(TimeEntry $entry): void
    {
        if (! $entry->payrollPeriod->status->isEditable()) {
            throw ValidationException::withMessages(['entry' => 'That week is locked; entries can no longer be edited.']);
        }

        $workOrderId = $entry->work_order_id;
        $periodId = $entry->payroll_period_id;

        $entry->delete();

        RecomputeTimeSummary::dispatchSync($workOrderId, $periodId);
    }
}
