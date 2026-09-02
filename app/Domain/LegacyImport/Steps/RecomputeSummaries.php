<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\Time\Jobs\RecomputeTimeSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Rebuild `time_summaries` for every imported (work_order, payroll_period) pair
 * (phase-final-cutover.md) — summaries are always derived, never imported.
 *
 * Notifications are faked for the whole pass: recomputing two years of history
 * crosses thousands of direct-hire thresholds, and NotifyDirectHireEligible
 * still stamps `direct_hire_notified_at` (so nobody is spammed after cutover)
 * while the fake swallows the actual sends.
 */
class RecomputeSummaries
{
    /** @return array<string, int> */
    public function handle(): array
    {
        Notification::fake();

        // Summaries are pure derivations — start clean so a re-run (or a prior
        // partial pass) leaves no stale rows for pairs that no longer exist.
        DB::table('time_summaries')->delete();

        $pairs = DB::table('time_entries')
            ->whereNull('deleted_at')
            ->distinct()
            ->get(['work_order_id', 'payroll_period_id']);

        foreach ($pairs as $pair) {
            (new RecomputeTimeSummary((int) $pair->work_order_id, (int) $pair->payroll_period_id))->handle();
        }

        return [
            'pairs_recomputed' => $pairs->count(),
            'summaries' => (int) DB::table('time_summaries')->count(),
        ];
    }
}
