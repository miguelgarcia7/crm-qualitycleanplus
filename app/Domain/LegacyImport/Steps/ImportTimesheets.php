<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `property_time_sheets` → `timesheets` (phase-final-cutover.md).
 *
 * One timesheet per payroll period. Status map: approved → approved (invoiced
 * once the invoices step links its invoice), pending → pending_approval,
 * rejected → declined. Open periods with no legacy timesheet (the in-flight
 * weeks) get the draft timesheet ADR-0007 promises so post-cutover flows work.
 */
class ImportTimesheets
{
    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'drafts_created' => 0, 'skipped_no_period' => 0];

        $periodIds = $this->periodIdsByPropertyWeek();

        DB::transaction(function () use ($periodIds, &$stats): void {
            foreach (DB::connection('legacy')->table('property_time_sheets')->orderBy('id')->get() as $sheet) {
                $propertyId = $this->map->newId('property', (int) $sheet->property_id);
                $weekStart = Carbon::parse($sheet->week_start)->toDateString();
                $periodId = $propertyId !== null ? ($periodIds[$propertyId][$weekStart] ?? null) : null;

                if ($periodId === null) {
                    $stats['skipped_no_period']++;

                    continue;
                }

                $status = match ($sheet->status) {
                    'approved' => 'approved', // invoices step flips to invoiced where an invoice exists
                    'rejected' => 'declined',
                    default => 'pending_approval', // pending / sent_back_for_approval
                };

                $statusBy = $this->map->newId('person', $sheet->status_updated_by !== null ? (int) $sheet->status_updated_by : null);

                $row = [
                    'property_id' => $propertyId,
                    'source' => 'clock_in',
                    'status' => $status,
                    'sent_for_approval_at' => $sheet->created_at,
                    'approved_at' => $sheet->status === 'approved' ? $sheet->status_updated_at : null,
                    'approved_by' => $sheet->status === 'approved' ? $statusBy : null,
                    'declined_at' => $sheet->status === 'rejected' ? $sheet->status_updated_at : null,
                    'declined_by' => $sheet->status === 'rejected' ? $statusBy : null,
                ];

                $existing = DB::table('timesheets')->where('payroll_period_id', $periodId)->first();

                if ($existing !== null) {
                    DB::table('timesheets')->where('id', $existing->id)->update($row);
                    $newId = (int) $existing->id;
                    $stats['updated']++;
                } else {
                    $newId = (int) DB::table('timesheets')->insertGetId($row + [
                        'payroll_period_id' => $periodId,
                        'created_at' => $sheet->created_at,
                        'updated_at' => $sheet->updated_at,
                    ]);
                    $stats['imported']++;
                }

                $this->map->remember('timesheet', (int) $sheet->id, $newId);
            }

            // Every open period carries a draft timesheet (ADR-0007) — the
            // in-flight weeks were created on demand by the time-entries step
            // and have no legacy timesheet yet.
            $openWithout = DB::table('payroll_periods')
                ->where('status', 'open')
                ->whereNotIn('id', DB::table('timesheets')->pluck('payroll_period_id'))
                ->get(['id', 'property_id']);

            foreach ($openWithout as $period) {
                DB::table('timesheets')->insert([
                    'property_id' => $period->property_id,
                    'payroll_period_id' => $period->id,
                    'source' => 'clock_in',
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stats['drafts_created']++;
            }
        });

        return $stats;
    }

    /** @return array<int, array<string, int>> property id => week_start => period id */
    private function periodIdsByPropertyWeek(): array
    {
        $result = [];

        foreach (DB::table('payroll_periods')->get(['id', 'property_id', 'week_start']) as $period) {
            $result[(int) $period->property_id][$period->week_start] = (int) $period->id;
        }

        return $result;
    }
}
