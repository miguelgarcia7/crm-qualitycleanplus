<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles a `legacy:import` run against the legacy database — the cutover
 * gate from docs/80-plan/phase-final-cutover.md.
 *
 * HARD checks must pass (exit 1 otherwise): row coverage, invoice money to the
 * cent, per-invoice equality, entry/period integrity, summary coverage.
 *
 * DRIFT reports quantify the known, accepted divergences: punches edited or
 * voided after their invoice froze (legacy drifted from its own invoices the
 * same way), the two apps' different overtime-split algorithms, the single
 * negative-duration punch clamped to zero, and the flagged legacy oddities
 * (pre-freeze zero-valued invoices, timesheet-less manual invoices).
 */
class LegacyVerify extends Command
{
    protected $signature = 'legacy:verify';

    protected $description = 'Reconcile the imported data against the legacy QC Minute database';

    private bool $failed = false;

    public function handle(): int
    {
        $legacy = DB::connection('legacy');

        $this->components->info('Hard checks');

        // — Coverage: every importable legacy row is accounted for.
        $legacyUsers = (int) $legacy->table('users')->count();
        $mappedPeople = (int) DB::table('legacy_id_map')->where('entity', 'person')->count();
        $deviceOnly = 1; // sole users-table device account (tablets live in `devices`)
        $dropped = 4;    // test rows (PeopleMergeMap::DROP)
        $this->check('people coverage', $mappedPeople === $legacyUsers - $deviceOnly - $dropped,
            "{$mappedPeople} mapped of {$legacyUsers} legacy users (1 device + 4 test rows excluded)");

        $this->check('properties', (int) DB::table('properties')->count() === (int) $legacy->table('properties')->count(),
            'all legacy properties imported');

        // Structural, not arithmetic: skipped work orders (incomplete rows, or
        // owned by dropped test users) are fine ONLY if nothing references them.
        $legacyWos = (int) $legacy->table('work_orders')->count();
        $importedWos = (int) DB::table('legacy_id_map')->where('entity', 'work_order')->count();
        $mappedIds = DB::table('legacy_id_map')->where('entity', 'work_order')->pluck('legacy_id');
        $unmappedWithHistory = (int) $legacy->table('work_orders')
            ->whereNotIn('id', $mappedIds)
            ->where(fn ($q) => $q
                ->whereExists(fn ($s) => $s->from('work_time_records')->whereColumn('work_time_records.work_order_id', 'work_orders.id'))
                ->orWhereExists(fn ($s) => $s->from('invoice_items')->whereColumn('invoice_items.work_order_id', 'work_orders.id')))
            ->count();
        $this->check('work orders', $unmappedWithHistory === 0,
            "{$importedWos} of {$legacyWos} imported; every skipped row verified free of punches and invoice lines");

        $legacyWtrs = (int) $legacy->table('work_time_records')->count();
        $importedEntries = (int) DB::table('time_entries')
            ->where('source', 'imported')->where('entry_type', 'work')
            ->where('source_metadata->legacy_table', 'work_time_records')->count();
        $this->check('time entries', $importedEntries === $legacyWtrs, "{$importedEntries} of {$legacyWtrs} punches");

        $orphanEntries = (int) DB::table('time_entries')
            ->whereNull('deleted_at')
            ->whereNotIn('payroll_period_id', DB::table('payroll_periods')->pluck('id'))
            ->count();
        $this->check('entry→period integrity', $orphanEntries === 0, 'every live entry sits in a payroll period');

        $legacyLiveInvoices = (int) $legacy->table('invoices')->whereNull('deleted_at')->count();
        $newInvoices = (int) DB::table('invoices')->count();
        $this->check('invoices', $newInvoices === $legacyLiveInvoices,
            "{$newInvoices} of {$legacyLiveInvoices} live legacy invoices (6 corrupt soft-deleted legacy rows excluded)");

        // — Money: to the cent, in aggregate and per invoice.
        $legacyMoney = $legacy->table('invoices')->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(total),0) t, COALESCE(SUM(work_subtotal),0) w, COALESCE(SUM(tax_amount),0) x')->first();
        $newMoney = DB::table('invoices')
            ->selectRaw('COALESCE(SUM(total),0) t, COALESCE(SUM(work_subtotal),0) w, COALESCE(SUM(tax_amount),0) x')->first();
        $this->check('invoice money (aggregate)',
            $legacyMoney->t === $newMoney->t && $legacyMoney->w === $newMoney->w && $legacyMoney->x === $newMoney->x,
            sprintf('total $%s / subtotal $%s / tax $%s', number_format($newMoney->t / 100, 2), number_format($newMoney->w / 100, 2), number_format($newMoney->x / 100, 2)));

        $perInvoiceMismatch = (int) DB::table('legacy_id_map as m')
            ->join('invoices as n', 'n.id', '=', 'm.new_id')
            ->where('m.entity', 'invoice')
            ->whereRaw('n.total <> (select COALESCE(i.total,0) from '.$this->legacyDb().'.invoices i where i.id = m.legacy_id)')
            ->count();
        $this->check('invoice money (per invoice)', $perInvoiceMismatch === 0, 'every imported invoice total equals its legacy total');

        $itemsMoneyLegacy = (int) $legacy->table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNull('invoices.deleted_at')->sum('invoice_items.total');
        $itemsMoneyNew = (int) DB::table('invoice_items')->sum('total_bill');
        $this->check('line-item money', $itemsMoneyLegacy === $itemsMoneyNew,
            sprintf('$%s billed across all frozen lines', number_format($itemsMoneyNew / 100, 2)));

        $paidLegacy = (int) $legacy->table('invoices')->whereNull('deleted_at')->where('status', 'paid')->count();
        $paidNew = (int) DB::table('invoices')->whereNotNull('paid_at')->count();
        $this->check('paid invoices', $paidLegacy === $paidNew, "{$paidNew} carried");

        $pairsWithEntries = (int) DB::table('time_entries')->whereNull('deleted_at')
            ->distinct()->count(DB::raw('CONCAT(work_order_id, ":", payroll_period_id)'));
        $summaries = (int) DB::table('time_summaries')->count();
        $this->check('summary coverage', $summaries === $pairsWithEntries,
            "{$summaries} summaries for {$pairsWithEntries} (work order, period) pairs");

        // — Known drift, quantified (informational).
        $this->newLine();
        $this->components->info('Known drift (accepted, not failures)');

        $clamped = $legacy->table('work_time_records')
            ->whereNull('deleted_at')->whereColumn('end_time_utc', '<', 'start_time_utc')->count();
        $this->line("  negative-duration punches clamped to 0: {$clamped}");

        $reattached = (int) DB::table('time_entries')->where('source_metadata->legacy_property_id', '!=', null)->count();
        $this->line("  punches re-attached from a sibling property to their work order's: {$reattached}");

        $drift = DB::table('time_summaries')
            ->selectRaw('payroll_period_id, SUM(regular_minutes+overtime_minutes+holiday_minutes+training_minutes) mins')
            ->groupBy('payroll_period_id')->get()->keyBy('payroll_period_id');
        $frozen = DB::table('invoices')
            ->selectRaw('payroll_period_id, SUM(total_regular_minutes+total_overtime_minutes+total_holiday_minutes+total_training_minutes) mins')
            ->groupBy('payroll_period_id')->get();
        $driftWeeks = 0;
        $driftMinutes = 0;

        foreach ($frozen as $week) {
            $current = (int) ($drift[$week->payroll_period_id]->mins ?? 0);

            if ($current !== (int) $week->mins) {
                $driftWeeks++;
                $driftMinutes += abs($current - (int) $week->mins);
            }
        }
        $this->line("  weeks where live entries differ from their frozen invoice: {$driftWeeks} ({$driftMinutes} min — punches edited/voided after invoicing; legacy has the same drift against itself)");

        $unfrozen = DB::table('invoices')->where('property_snapshot->legacy_unfrozen', true)->pluck('invoice_number');
        $this->line('  pre-freeze invoices imported zero-valued: '.($unfrozen->isEmpty() ? 'none' : $unfrozen->implode(', ')));

        $manual = DB::table('invoices')->whereNull('timesheet_id')->pluck('invoice_number');
        $this->line('  timesheet-less manual invoices: '.($manual->isEmpty() ? 'none' : $manual->implode(', ')));

        $openPunches = (int) DB::table('time_entries')->whereNull('deleted_at')->whereNull('end_at_utc')->where('entry_type', 'work')->count();
        $this->line("  open punches (people on the clock at dump time): {$openPunches}");

        $this->newLine();

        if ($this->failed) {
            $this->components->error('Verification FAILED — do not cut over on this run.');

            return self::FAILURE;
        }

        $this->components->info('All hard checks passed.');

        return self::SUCCESS;
    }

    private function check(string $name, bool $ok, string $detail): void
    {
        if ($ok) {
            $this->line("  ✓ {$name} — {$detail}");
        } else {
            $this->failed = true;
            $this->line("  ✗ {$name} — {$detail}");
        }
    }

    private function legacyDb(): string
    {
        return (string) config('database.connections.legacy.database');
    }
}
