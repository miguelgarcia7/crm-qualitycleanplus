<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `property_time_sheets` weeks → `payroll_periods` (phase-final-cutover.md).
 *
 * Legacy weeks come with explicit start/end dates, so periods import verbatim —
 * no cadence math (both apps anchor weeks on the property's closing weekday, ISO
 * 1–7, so the dates already agree). Historical weeks arrive `closed`; the weeks
 * covering today or later arrive `open` so live punches, timesheet review, and
 * charge allocation keep working after cutover. The invoices step later flips
 * invoiced weeks to `invoiced`.
 *
 * A stray punch in a week with no legacy timesheet gets its period created on
 * demand by the time-entries step.
 */
class ImportPayrollPeriods
{
    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped_unmapped' => 0, 'open' => 0];
        $today = now()->toDateString();

        $weeks = DB::connection('legacy')->table('property_time_sheets')
            ->select('property_id', 'week_start', 'week_end')
            ->distinct()
            ->orderBy('week_start')
            ->get();

        DB::transaction(function () use ($weeks, $today, &$stats): void {
            foreach ($weeks as $week) {
                $propertyId = $this->map->newId('property', (int) $week->property_id);

                if ($propertyId === null) {
                    $stats['skipped_unmapped']++;

                    continue;
                }

                $weekStart = Carbon::parse($week->week_start)->toDateString();
                $weekEnd = Carbon::parse($week->week_end)->toDateString();
                $isOpen = $weekEnd >= $today;

                $existing = DB::table('payroll_periods')
                    ->where('property_id', $propertyId)
                    ->where('week_start', $weekStart)
                    ->first();

                $row = [
                    'week_end' => $weekEnd,
                    'status' => $isOpen ? 'open' : 'closed',
                ];

                if ($existing !== null) {
                    DB::table('payroll_periods')->where('id', $existing->id)->update($row);
                    $stats['updated']++;
                } else {
                    DB::table('payroll_periods')->insert($row + [
                        'property_id' => $propertyId,
                        'week_start' => $weekStart,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['imported']++;
                }

                if ($isOpen) {
                    $stats['open']++;
                }
            }
        });

        return $stats;
    }
}
