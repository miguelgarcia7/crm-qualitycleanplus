<?php

namespace App\Console\Commands;

use App\Domain\Time\Models\TimeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One year after a punch, its clock-in and clock-out selfies and GPS are
 * deleted; the punch stays, with its times, hours and rates, so timesheets,
 * invoices and payroll don't change — audit-and-pii.md, Time entry GPS
 * retention (ADR-0010). Punches of someone on legal hold are skipped.
 */
class PurgeTimeEntryLocations extends Command
{
    protected $signature = 'time-entries:purge';

    protected $description = 'Delete selfies and GPS from clock-in punches older than a year';

    public function handle(): int
    {
        $purged = 0;

        TimeEntry::withTrashed()
            ->where('start_at_utc', '<', now()->subYear())
            ->where(fn ($q) => $q->whereNotNull('clock_in_selfie_file_id')
                ->orWhereNotNull('clock_out_selfie_file_id')
                ->orWhereNotNull('clock_in_gps_lat')
                ->orWhereNotNull('clock_out_gps_lat'))
            ->whereDoesntHave('person', fn ($q) => $q->where('legal_hold', true))
            ->with(['clockInSelfie' => fn ($q) => $q->withTrashed(), 'clockOutSelfie' => fn ($q) => $q->withTrashed()])
            ->chunkById(200, function ($entries) use (&$purged): void {
                foreach ($entries as $entry) {
                    // Straight to the table: the punch's hours and pay aren't
                    // changing, so no recompute and no new updated_at.
                    TimeEntry::withTrashed()->whereKey($entry->id)->toBase()->update([
                        'clock_in_selfie_file_id' => null,
                        'clock_in_gps_lat' => null,
                        'clock_in_gps_lng' => null,
                        'clock_in_gps_accuracy_meters' => null,
                        'clock_out_selfie_file_id' => null,
                        'clock_out_gps_lat' => null,
                        'clock_out_gps_lng' => null,
                        'clock_out_gps_accuracy_meters' => null,
                    ]);

                    foreach ([$entry->clockInSelfie, $entry->clockOutSelfie] as $file) {
                        if ($file !== null) {
                            Storage::disk($file->disk)->delete($file->path);
                            $file->forceDelete();
                        }
                    }

                    $purged++;
                }
            });

        $this->info("Purged selfies and GPS from {$purged} ".($purged === 1 ? 'punch' : 'punches').'.');

        return self::SUCCESS;
    }
}
