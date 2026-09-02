<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Legacy `job_types` → `positions`, and `property_job_type.job_coding` →
 * `property_position_codes` (phase-final-cutover.md).
 *
 * Positions are a global catalog here, so legacy job types merge by slug into
 * whatever the structural seeder already created — two legacy spellings that
 * slugify identically fold into one position, and both legacy ids map to it.
 * Soft-deleted job types import as is_active = false (history references them).
 */
class ImportPositions
{
    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $legacy = DB::connection('legacy');
        $stats = ['imported' => 0, 'matched' => 0, 'codes' => 0, 'codes_skipped' => 0];

        DB::transaction(function () use ($legacy, &$stats): void {
            foreach ($legacy->table('job_types')->orderBy('id')->get() as $jobType) {
                $name = trim($jobType->name);
                $slug = Str::slug($name);

                if ($slug === '') {
                    continue;
                }

                $existingId = DB::table('positions')->where('slug', $slug)->value('id');

                if ($existingId !== null) {
                    $stats['matched']++;
                } else {
                    $existingId = DB::table('positions')->insertGetId([
                        'name' => $name,
                        'slug' => $slug,
                        'is_active' => $jobType->deleted_at === null,
                        'created_at' => $jobType->created_at ?? now(),
                        'updated_at' => $jobType->updated_at ?? now(),
                    ]);
                    $stats['imported']++;
                }

                $this->map->remember('position', (int) $jobType->id, (int) $existingId);
            }

            foreach ($legacy->table('property_job_type')->get() as $link) {
                $propertyId = $this->map->newId('property', (int) $link->property_id);
                $positionId = $this->map->newId('position', (int) $link->job_type_id);
                $code = trim((string) ($link->job_coding ?? ''));

                if ($propertyId === null || $positionId === null || $code === '') {
                    $stats['codes_skipped']++;

                    continue;
                }

                DB::table('property_position_codes')->updateOrInsert(
                    ['property_id' => $propertyId, 'position_id' => $positionId],
                    ['job_code' => mb_substr($code, 0, 64), 'updated_at' => now(), 'created_at' => now()],
                );
                $stats['codes']++;
            }
        });

        return $stats;
    }
}
