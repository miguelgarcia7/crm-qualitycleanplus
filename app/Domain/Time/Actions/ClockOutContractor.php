<?php

namespace App\Domain\Time\Actions;

use App\Domain\Time\Events\TimeEntrySaved;
use App\Domain\Time\Jobs\RecomputeTimeSummary;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Support\StoreSelfie;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Closes an open QR clock-in entry (Phase 07a, ADR-0017): stamps the end time +
 * duration, captures clock-out GPS + selfie, then recomputes the week's summary so
 * the now-billable hours flow into time summaries / invoicing. Geofence is not
 * enforced on clock-out (the contractor is leaving).
 *
 * @phpstan-type ClockData array{lat: float, lng: float, accuracy?: int|null, selfie: UploadedFile}
 */
class ClockOutContractor
{
    /**
     * @param  ClockData  $data
     */
    public function handle(TimeEntry $entry, array $data): TimeEntry
    {
        $end = CarbonImmutable::now('UTC');
        $start = CarbonImmutable::parse($entry->start_at_utc);
        $duration = max(0, (int) $start->diffInMinutes($end));

        $entry->update([
            'end_at_utc' => $end,
            'duration_minutes' => $duration,
            'clock_out_gps_lat' => $data['lat'],
            'clock_out_gps_lng' => $data['lng'],
            'clock_out_gps_accuracy_meters' => $data['accuracy'] ?? null,
        ]);

        $entry->update(['clock_out_selfie_file_id' => StoreSelfie::for($data['selfie'], $entry, $entry->person_id)->id]);

        RecomputeTimeSummary::dispatchSync($entry->work_order_id, $entry->payroll_period_id);

        TimeEntrySaved::dispatch($entry->property_id, $entry->payrollPeriod->week_start->toDateString());

        return $entry;
    }
}
