<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Actions\VoidInvoice;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\People\Models\Person;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rolls back an applied import (40-flows/import-hours.md §Rollback): voids the
 * invoice (reopening the period), soft-deletes the imported time entries and the
 * adjustments the import created, recomputes the now-empty week summaries, and
 * marks the batch rolled_back. The batch + rows are preserved for audit. Work
 * orders the import created are left in place (spec-compliant).
 */
class RollbackImport
{
    public function __construct(private readonly VoidInvoice $voidInvoice) {}

    public function handle(ImportBatch $batch, Person $user, string $reason = 'Import rolled back'): void
    {
        if ($batch->status !== ImportBatchStatus::Applied) {
            throw ValidationException::withMessages(['import' => 'Only an applied import can be rolled back.']);
        }

        DB::transaction(function () use ($batch, $user, $reason): void {
            if ($batch->invoice !== null) {
                $this->voidInvoice->handle($batch->invoice, $user, $reason);
            }

            $entries = TimeEntry::query()
                ->where('source', TimeEntrySource::Imported->value)
                ->where('source_metadata->import_batch_id', $batch->id)
                ->get();

            $workOrderIds = $entries->pluck('work_order_id')->unique()->values();

            $adjustmentIds = $batch->stats['adjustment_ids'] ?? [];
            if ($adjustmentIds !== []) {
                TimeEntryAdjustment::query()->whereIn('id', $adjustmentIds)->delete();
            }

            TimeEntry::query()->whereIn('id', $entries->pluck('id'))->delete();

            foreach ($workOrderIds as $workOrderId) {
                RecomputeTimeSummary::dispatchSync((int) $workOrderId, $batch->payroll_period_id);
            }

            $batch->update([
                'status' => ImportBatchStatus::RolledBack,
                'rolled_back_at' => now(),
                'rolled_back_by' => $user->id,
            ]);

            activity()->performedOn($batch)->causedBy($user)->log(
                "Import rolled back: {$entries->count()} time entries removed",
            );
        });
    }
}
