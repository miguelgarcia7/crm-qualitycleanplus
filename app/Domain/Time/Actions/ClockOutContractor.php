<?php

namespace App\Domain\Time\Actions;

use App\Domain\Time\Events\TimeEntrySaved;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Support\GpsPolicy;
use App\Domain\Time\Support\StoreSelfie;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Closes an open QR clock-in entry (Phase 07a, ADR-0017): stamps the end time +
 * duration, captures clock-out GPS + selfie, then recomputes the week's summary so
 * the now-billable hours flow into time summaries / invoicing. Clock-out NEVER
 * blocks (the worked time already exists and the contractor is leaving) — but
 * degraded GPS, and even a trusted fix outside the fence, flag the entry and
 * notify the recruiters for review.
 *
 * @phpstan-type ClockData array{lat?: float|null, lng?: float|null, accuracy?: int|null, gps_failure_reason?: string|null, selfie?: UploadedFile|null}
 */
class ClockOutContractor
{
    /**
     * @param  ClockData  $data
     */
    public function handle(TimeEntry $entry, array $data, bool $evaluateGps = true): TimeEntry
    {
        $end = CarbonImmutable::now('UTC');
        $start = CarbonImmutable::parse($entry->start_at_utc);
        $duration = max(0, (int) $start->diffInMinutes($end));

        $flagReason = null;
        if ($evaluateGps && $entry->property !== null) {
            $gps = GpsPolicy::evaluate($entry->property, $data['lat'] ?? null, $data['lng'] ?? null, $data['accuracy'] ?? null, $data['gps_failure_reason'] ?? null);
            $flagReason = $gps['outcome'] === GpsPolicy::OUTCOME_BLOCKED ? 'outside_geofence' : $gps['flag_reason'];
        }

        $entry->update([
            'end_at_utc' => $end,
            'duration_minutes' => $duration,
            'clock_out_gps_lat' => $data['lat'] ?? null,
            'clock_out_gps_lng' => $data['lng'] ?? null,
            'clock_out_gps_accuracy_meters' => $data['accuracy'] ?? null,
            'clock_out_gps_flag_reason' => $flagReason,
        ]);

        if ($flagReason !== null) {
            ClockInContractor::notifyRecruiters($entry, 'out', $flagReason);
        }

        if (isset($data['selfie'])) {
            $entry->update(['clock_out_selfie_file_id' => StoreSelfie::for($data['selfie'], $entry, $entry->person_id)->id]);
        }

        RecomputeTimeSummary::dispatchSync($entry->work_order_id, $entry->payroll_period_id);

        TimeEntrySaved::dispatch($entry->property_id, $entry->payrollPeriod->week_start->toDateString());

        return $entry;
    }
}
