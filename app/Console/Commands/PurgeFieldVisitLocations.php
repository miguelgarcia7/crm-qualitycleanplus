<?php

namespace App\Console\Commands;

use App\Domain\FieldVisits\Models\FieldVisit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One year after a field visit, its selfie and GPS are deleted; the visit row
 * stays ("Jane visited Marriott on May 21 at 10:14 AM") — audit-and-pii.md,
 * Field visit purge (ADR-0010). Visits of someone on legal hold are skipped.
 */
class PurgeFieldVisitLocations extends Command
{
    protected $signature = 'field-visits:purge';

    protected $description = 'Delete selfies and GPS from field visits older than a year';

    public function handle(): int
    {
        $purged = 0;

        FieldVisit::query()
            ->where('check_in_at', '<', now()->subYear())
            ->where(fn ($q) => $q->whereNotNull('check_in_selfie_file_id')
                ->orWhereNotNull('check_in_gps_lat')
                ->orWhereNotNull('check_out_gps_lat'))
            ->whereDoesntHave('person', fn ($q) => $q->where('legal_hold', true))
            ->with(['checkInSelfie' => fn ($q) => $q->withTrashed()])
            ->chunkById(200, function ($visits) use (&$purged): void {
                foreach ($visits as $visit) {
                    $file = $visit->checkInSelfie;

                    $visit->update([
                        'check_in_selfie_file_id' => null,
                        'check_in_gps_lat' => null,
                        'check_in_gps_lng' => null,
                        'check_in_gps_accuracy_meters' => null,
                        'check_out_gps_lat' => null,
                        'check_out_gps_lng' => null,
                        'check_out_gps_accuracy_meters' => null,
                    ]);

                    if ($file !== null) {
                        Storage::disk($file->disk)->delete($file->path);
                        $file->forceDelete();
                    }

                    $purged++;
                }
            });

        $this->info("Purged selfies and GPS from {$purged} ".($purged === 1 ? 'visit' : 'visits').'.');

        return self::SUCCESS;
    }
}
