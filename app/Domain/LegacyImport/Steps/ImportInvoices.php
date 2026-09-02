<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `invoices` + `invoice_items` → same, verbatim (phase-final-cutover.md).
 *
 * Snapshots and money copy as-is — never recomputed; these are frozen financial
 * records. Status: every live legacy invoice was issued, so they arrive
 * `invoiced`; the legacy paid/pending/overdue status becomes `paid_at`
 * (overdue is derived from due_date + unpaid). The six soft-deleted legacy
 * invoices are the known-bad orphans legacy itself purged (no snapshot, no
 * timesheet, no week) — skipped, and reported.
 *
 * Legacy manual invoices (no timesheet; several can share one week) import with
 * timesheet_id NULL against their week's period — the column is nullable for
 * exactly this imported case. Six early invoices predate the legacy snapshot
 * freeze and have no stored money at all; they import zero-valued with a
 * `legacy_unfrozen` marker in the snapshot for the verify report.
 */
class ImportInvoices
{
    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $legacy = DB::connection('legacy');
        $stats = [
            'imported' => 0, 'updated' => 0, 'items' => 0, 'skipped_junk' => 0,
            'manual_no_timesheet' => 0, 'unfrozen_zeroed' => 0, 'periods_created' => 0,
        ];

        // Legacy timesheet id per invoice id (a legacy sheet points AT its invoice).
        $sheetByInvoice = $legacy->table('property_time_sheets')
            ->whereNotNull('invoice_id')
            ->pluck('id', 'invoice_id')
            ->all();

        $properties = Property::query()->whereIn(
            'id',
            array_values($this->map->all('property')),
        )->get()->keyBy('id');

        DB::transaction(function () use ($legacy, $sheetByInvoice, $properties, &$stats): void {
            foreach ($legacy->table('invoices')->orderBy('id')->get() as $invoice) {
                if ($invoice->deleted_at !== null) {
                    $stats['skipped_junk']++;

                    continue;
                }

                $propertyId = $this->map->newId('property', (int) $invoice->property_id);
                $timesheetId = $this->map->newId('timesheet', $sheetByInvoice[$invoice->id] ?? null);

                if ($propertyId === null) {
                    continue; // cannot happen — every property imports; guarded for reruns
                }

                $periodId = $timesheetId !== null
                    ? (int) DB::table('timesheets')->where('id', $timesheetId)->value('payroll_period_id')
                    : $this->periodForWeek($properties->get($propertyId), $invoice->week_start, $stats);

                if ($timesheetId === null) {
                    $stats['manual_no_timesheet']++;
                }

                $unfrozen = $invoice->property_snapshot === null;

                if ($unfrozen) {
                    $stats['unfrozen_zeroed']++;
                }

                $row = [
                    'property_id' => $propertyId,
                    'payroll_period_id' => $periodId,
                    'timesheet_id' => $timesheetId,
                    'invoice_number' => sprintf('QCM-%05d', $invoice->id),
                    'issue_date' => Carbon::parse($invoice->created_at)->toDateString(),
                    'due_date' => Carbon::parse($invoice->due_date)->toDateString(),
                    'paid_at' => $invoice->status === 'paid' ? $invoice->updated_at : null,
                    'property_snapshot' => $unfrozen
                        ? json_encode(['name' => $properties->get($propertyId)?->name, 'legacy_unfrozen' => true])
                        : $invoice->property_snapshot,
                    'invoicer_snapshot' => $invoice->invoicer_snapshot ?? json_encode(['legacy_unfrozen' => true]),
                    'tax_rate' => $invoice->tax_rate ?? 0,
                    'work_subtotal' => (int) ($invoice->work_subtotal ?? 0),
                    'adjustment_total' => (int) ($invoice->adjustment_total ?? 0),
                    'subtotal' => (int) ($invoice->subtotal ?? 0),
                    'tax_amount' => (int) ($invoice->tax_amount ?? 0),
                    'total' => (int) ($invoice->total ?? 0),
                    'total_regular_minutes' => (int) ($invoice->total_regular_minutes ?? 0),
                    'total_overtime_minutes' => (int) ($invoice->total_overtime_minutes ?? 0),
                    'total_holiday_minutes' => (int) ($invoice->total_holiday_minutes ?? 0),
                    'total_training_minutes' => (int) ($invoice->total_training_minutes ?? 0),
                    'status' => 'invoiced',
                    'frozen_at' => $invoice->created_at,
                ];

                $existingId = $this->map->newId('invoice', (int) $invoice->id);

                if ($existingId !== null) {
                    DB::table('invoices')->where('id', $existingId)->update($row);
                    $newId = $existingId;
                    $stats['updated']++;
                } else {
                    $newId = (int) DB::table('invoices')->insertGetId($row + [
                        'created_at' => $invoice->created_at,
                        'updated_at' => $invoice->updated_at,
                    ]);
                    $this->map->remember('invoice', (int) $invoice->id, $newId);
                    $stats['imported']++;
                }

                if ($timesheetId !== null) {
                    DB::table('timesheets')->where('id', $timesheetId)
                        ->update(['invoice_id' => $newId, 'status' => 'invoiced']);
                }

                DB::table('payroll_periods')->where('id', $periodId)->where('status', 'closed')
                    ->update(['status' => 'invoiced', 'invoiced_at' => $invoice->created_at]);

                $stats['items'] += $this->reloadItems((int) $invoice->id, $newId);
            }
        });

        return $stats;
    }

    /** Wipe-and-reload the frozen line items for one invoice. */
    private function reloadItems(int $legacyInvoiceId, int $newInvoiceId): int
    {
        DB::table('invoice_items')->where('invoice_id', $newInvoiceId)->delete();

        $rows = [];

        foreach (DB::connection('legacy')->table('invoice_items')->where('invoice_id', $legacyInvoiceId)->get() as $item) {
            $rows[] = [
                'invoice_id' => $newInvoiceId,
                'work_order_id' => $this->map->newId('work_order', (int) $item->work_order_id) ?? 0,
                'contractor_name' => $item->contractor_name,
                'position_name' => $item->job_type_name,
                'job_code' => $item->job_coding !== null ? mb_substr($item->job_coding, 0, 64) : null,
                'pay_rate' => (int) $item->contractor_rate,
                'ot_pay_rate' => (int) round(((int) $item->contractor_rate) * 1.5),
                'bill_rate' => (int) $item->rate,
                'ot_bill_rate' => (int) ($item->overtime_rate ?? round(((int) $item->rate) * 1.5)),
                'regular_minutes' => (int) $item->regular_minutes,
                'overtime_minutes' => (int) $item->overtime_minutes,
                'holiday_minutes' => (int) $item->holiday_minutes,
                'training_minutes' => (int) $item->training_minutes,
                'regular_amount_bill' => (int) $item->regular_amount,
                'overtime_amount_bill' => (int) $item->overtime_amount,
                'holiday_amount_bill' => (int) $item->holiday_amount,
                'training_amount_bill' => (int) $item->training_amount,
                'total_bill' => (int) $item->total,
                'total_payout' => (int) $item->payouts,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('invoice_items')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Period for a timesheet-less invoice: the canonical week (property cadence)
     * containing the invoice's week_start, created closed if it does not exist.
     *
     * @param  array<string, int>  $stats
     */
    private function periodForWeek(?Property $property, ?string $weekStart, array &$stats): int
    {
        if ($property === null || $weekStart === null) {
            throw new \RuntimeException('Timesheet-less legacy invoice without week_start — cannot place it in a period.');
        }

        $start = $property->weekStartFor(Carbon::parse($weekStart)->toDateString());

        $existing = DB::table('payroll_periods')
            ->where('property_id', $property->id)
            ->where('week_start', $start->toDateString())
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $stats['periods_created']++;

        return (int) DB::table('payroll_periods')->insertGetId([
            'property_id' => $property->id,
            'week_start' => $start->toDateString(),
            'week_end' => $start->addDays(6)->toDateString(),
            'status' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
