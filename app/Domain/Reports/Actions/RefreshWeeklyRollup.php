<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Reports\Models\ReportWeeklyRollup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the (property, week) cell of report_weekly_rollups from
 * time_summaries grouped by the work order's position (ADR-0028). Idempotent:
 * delete-and-rebuild, so vanished summaries vanish from the rollup too.
 */
class RefreshWeeklyRollup
{
    public function handle(int $propertyId, string $weekStart): void
    {
        $weekStart = CarbonImmutable::parse($weekStart)->toDateString();

        $rows = DB::table('time_summaries')
            ->join('work_orders', 'work_orders.id', '=', 'time_summaries.work_order_id')
            ->where('time_summaries.property_id', $propertyId)
            ->whereDate('time_summaries.week_start', $weekStart)
            ->groupBy('work_orders.position_id')
            ->select([
                'work_orders.position_id',
                DB::raw('SUM(time_summaries.regular_minutes) as regular_minutes'),
                DB::raw('SUM(time_summaries.overtime_minutes) as overtime_minutes'),
                DB::raw('SUM(time_summaries.holiday_minutes) as holiday_minutes'),
                DB::raw('SUM(time_summaries.training_minutes) as training_minutes'),
                DB::raw('SUM(time_summaries.total_pay) as total_pay'),
                DB::raw('SUM(time_summaries.total_bill) as total_bill'),
                DB::raw('COUNT(DISTINCT time_summaries.person_id) as contractor_count'),
            ])
            ->get();

        DB::transaction(function () use ($propertyId, $weekStart, $rows): void {
            ReportWeeklyRollup::query()
                ->where('property_id', $propertyId)
                ->whereDate('week_start', $weekStart)
                ->delete();

            foreach ($rows as $row) {
                $minutes = (int) $row->regular_minutes + (int) $row->overtime_minutes
                    + (int) $row->holiday_minutes + (int) $row->training_minutes;

                ReportWeeklyRollup::create([
                    'property_id' => $propertyId,
                    'position_id' => (int) $row->position_id,
                    'week_start' => $weekStart,
                    'week_end' => CarbonImmutable::parse($weekStart)->addDays(6)->toDateString(),
                    'regular_minutes' => (int) $row->regular_minutes,
                    'overtime_minutes' => (int) $row->overtime_minutes,
                    'holiday_minutes' => (int) $row->holiday_minutes,
                    'training_minutes' => (int) $row->training_minutes,
                    'total_minutes' => $minutes,
                    'total_pay' => (int) $row->total_pay,
                    'total_bill' => (int) $row->total_bill,
                    'contractor_count' => (int) $row->contractor_count,
                    'last_refreshed_at' => now(),
                ]);
            }
        });
    }
}
