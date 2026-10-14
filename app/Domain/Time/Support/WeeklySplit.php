<?php

namespace App\Domain\Time\Support;

/**
 * The weekly 40-hour split (20-domain/time-tracking.md): a week's work minutes
 * by local day become regular, overtime and holiday minutes. Days are taken in
 * order; a holiday's minutes all go to the holiday bucket (never overtime) but
 * still count toward the 40 hours; other days fill regular up to the threshold
 * and the rest is overtime. Minutes with no day (an imported weekly total) come
 * last and are never holiday. Shared by RecomputeTimeSummary and the hour-import
 * review, so the preview matches what payroll records.
 */
final class WeeklySplit
{
    /**
     * @param  array<string, int>  $minutesByDay  local date (Y-m-d) => minutes
     * @param  array<string, string>  $holidayDates  local date => holiday name
     * @return array{regular: int, overtime: int, holiday: int}
     */
    public static function split(array $minutesByDay, array $holidayDates, int $undatedMinutes = 0, ?int $threshold = null): array
    {
        $threshold ??= (int) config('qcp.time.overtime_weekly_threshold_minutes', 2400);
        ksort($minutesByDay);

        $regular = 0;
        $overtime = 0;
        $holiday = 0;
        $running = 0;
        $days = array_map(null, array_keys($minutesByDay), array_values($minutesByDay));
        if ($undatedMinutes > 0) {
            $days[] = [null, $undatedMinutes];
        }

        foreach ($days as [$day, $minutes]) {
            if ($day !== null && isset($holidayDates[$day])) {
                $holiday += $minutes;
                $running += $minutes;

                continue;
            }
            $room = max(0, $threshold - $running);
            $reg = min($minutes, $room);
            $regular += $reg;
            $overtime += $minutes - $reg;
            $running += $minutes;
        }

        return ['regular' => $regular, 'overtime' => $overtime, 'holiday' => $holiday];
    }
}
