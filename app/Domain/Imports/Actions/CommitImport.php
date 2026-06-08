<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Adjustments\Actions\CreateManualAdjustment;
use App\Domain\Billing\Actions\GenerateInvoice;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Imports\Models\ImportBatchRow;
use App\Domain\Imports\Support\ResolvesImportRates;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateImportedTimeEntry;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Actions\CreateWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Commits a previewed import (40-flows/import-hours.md §Step 5). In one transaction:
 * resolve/create a work order per row, create imported time entries (recomputing the
 * week's summary), apply staged adjustments, then create an auto-approved timesheet
 * and generate the frozen invoice. Import-only properties skip the PM approval gate
 * (ADR-0007) — the commit is the approval.
 */
class CommitImport
{
    use ResolvesImportRates;

    public function __construct(
        private readonly CreateWorkOrder $createWorkOrder,
        private readonly CreateImportedTimeEntry $createTimeEntry,
        private readonly CreateManualAdjustment $createAdjustment,
        private readonly GenerateInvoice $generateInvoice,
    ) {}

    public function handle(ImportBatch $batch, Person $user): Invoice
    {
        if ($batch->status !== ImportBatchStatus::Preview) {
            throw ValidationException::withMessages(['import' => 'This import has already been processed.']);
        }

        $batch->loadMissing('property', 'payrollPeriod', 'rows.existingWorkOrder');
        $period = $batch->payrollPeriod;
        $property = $batch->property;

        if (! $period->status->isEditable()) {
            throw ValidationException::withMessages(['import' => 'The payroll period for this week is no longer open.']);
        }

        $this->assertAllResolved($batch, $property);

        return DB::transaction(function () use ($batch, $period, $property, $user): Invoice {
            $rows = $batch->rows()->committable()->get();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['import' => 'There are no rows to import.']);
            }

            /** @var array<int, WorkOrder> $rowWorkOrders */
            $rowWorkOrders = [];

            foreach ($rows as $row) {
                $workOrder = $this->resolveWorkOrder($row, $property, $period, $user);
                $rowWorkOrders[$row->id] = $workOrder;

                $entry = $this->createTimeEntry->handle($workOrder, [
                    'duration_minutes' => (int) round(((float) $row->raw_data['hours']) * 60),
                    'payroll_period_id' => $period->id,
                    'source_metadata' => [
                        'import_batch_id' => $batch->id,
                        'row_number' => $row->row_number,
                        'original_external_id' => $row->raw_data['external_id'] ?? null,
                        'original_file_data' => $row->raw_data,
                    ],
                ], $user);

                $row->update([
                    'status' => ImportRowStatus::Applied,
                    'resulting_work_order_id' => $workOrder->id,
                    'resulting_time_entry_id' => $entry->id,
                ]);
            }

            $adjustmentIds = $this->applyAdjustments($batch, $period, $rowWorkOrders, $user);

            $timesheet = Timesheet::create([
                'property_id' => $property->id,
                'payroll_period_id' => $period->id,
                'source' => 'imported',
                'status' => TimesheetStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);

            $invoice = $this->generateInvoice->handle($timesheet);

            $stats = [
                'entries' => $rows->count(),
                'work_orders' => count(array_unique(array_map(static fn (WorkOrder $w): int => $w->id, $rowWorkOrders))),
                'adjustments' => count($adjustmentIds),
                'adjustment_ids' => $adjustmentIds,
                'invoice_total' => $invoice->total,
            ];

            $batch->update([
                'status' => ImportBatchStatus::Applied,
                'invoice_id' => $invoice->id,
                'applied_at' => now(),
                'applied_by' => $user->id,
                'stats' => $stats,
            ]);

            activity()->performedOn($batch)->causedBy($user)->log(
                "Import committed: {$stats['entries']} time entries, invoice {$invoice->invoice_number}",
            );

            return $invoice;
        });
    }

    private function resolveWorkOrder(ImportBatchRow $row, Property $property, PayrollPeriod $period, Person $user): WorkOrder
    {
        $resolution = $this->effectiveResolution($row);

        if ($resolution === 'use_existing') {
            $existing = $row->existingWorkOrder;
            if ($existing === null) {
                throw ValidationException::withMessages(['import' => "Row {$row->row_number} has no work order to reuse."]);
            }

            return $existing;
        }

        $rates = $this->effectiveRates($row->raw_data, $property, $row->existingWorkOrder, $resolution);

        if ($rates['position_id'] === null) {
            throw ValidationException::withMessages(['import' => "Row {$row->row_number} needs a position before it can be imported."]);
        }
        if ($rates['bill'] === null) {
            throw ValidationException::withMessages([
                'import' => "Row {$row->row_number} needs a bill rate — add one to the file or set a property rate for the position.",
            ]);
        }

        // Replacing an existing WO at a new rate — close the old one the day before the week.
        if ($resolution === 'use_file' && $row->existingWorkOrder !== null) {
            $row->existingWorkOrder->update([
                'status' => WorkOrderStatus::Closed,
                'end_date' => $period->week_start->subDay()->toDateString(),
            ]);
        }

        return $this->createWorkOrder->handle([
            'person_id' => $row->matched_person_id,
            'property_id' => $property->id,
            'position_id' => $rates['position_id'],
            'pay_rate' => $rates['pay'],
            'bill_rate' => $rates['bill'],
            'ot_pay_rate' => $rates['ot_pay'],
            'ot_bill_rate' => $rates['ot_bill'],
            'start_date' => $period->week_start->toDateString(),
            'status' => WorkOrderStatus::Active,
        ], $user, WorkOrderSource::Imported);
    }

    /**
     * @param  array<int, WorkOrder>  $rowWorkOrders
     * @return list<int> ids of the adjustments created (for rollback)
     */
    private function applyAdjustments(ImportBatch $batch, PayrollPeriod $period, array $rowWorkOrders, Person $user): array
    {
        $ids = [];

        foreach ($batch->pending_adjustments ?? [] as $adj) {
            $workOrder = $rowWorkOrders[$adj['row_id']] ?? null;
            if ($workOrder === null) {
                continue;
            }

            $adjustment = $this->createAdjustment->handle($period, [
                'person_id' => $workOrder->person_id,
                'work_order_id' => $workOrder->id,
                'adjustment_item_id' => $adj['adjustment_item_id'] ?? null,
                'value' => (int) $adj['value'],
                'type' => (string) $adj['type'],
                'is_billable' => (bool) ($adj['is_billable'] ?? false),
                'notes' => $adj['notes'] ?? null,
            ], $user);

            $ids[] = $adjustment->id;
        }

        return $ids;
    }

    private function assertAllResolved(ImportBatch $batch, Property $property): void
    {
        foreach ($batch->rows as $row) {
            if (in_array($row->status, [ImportRowStatus::Skipped, ImportRowStatus::Applied], true)) {
                continue;
            }

            $unresolved = $row->status === ImportRowStatus::Unmatched
                || ($row->status === ImportRowStatus::RateConflict && $row->resolution === null);
            $needsPosition = $this->willCreateWorkOrder($row) && $this->resolvePositionId($row->raw_data, $property) === null;

            if ($unresolved || $needsPosition) {
                throw ValidationException::withMessages([
                    'import' => "Row {$row->row_number} still needs to be resolved before committing.",
                ]);
            }
        }
    }
}
