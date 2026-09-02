<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `files` → `files` (phase-final-cutover.md).
 *
 * All legacy files live on the `spaces` object-storage disk, so only the rows
 * move — the bytes stay where they are, and this app reaches them through a
 * disk of the same name. Morph targets are rewritten from the legacy FQCNs:
 * punch selfies/attachments (WorkTimeRecord) re-attach to their imported
 * TimeEntry, user documents to Person, property files to Property.
 */
class ImportFiles
{
    private const CHUNK = 1000;

    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $stats = ['imported' => 0, 'skipped_unmapped' => 0];

        // legacy work_time_record id => imported time entry id, from the audit
        // metadata the time-entries step wrote.
        $entryByLegacyId = [];

        $entryRows = DB::table('time_entries')
            ->where('source', 'imported')
            ->where('source_metadata->legacy_table', 'work_time_records')
            ->where('entry_type', 'work')
            ->selectRaw("id, source_metadata->>'$.legacy_id' as legacy_id")
            ->get();

        foreach ($entryRows as $row) {
            $entryByLegacyId[(int) $row->legacy_id] = (int) $row->id;
        }

        $people = $this->map->all('person');
        $properties = $this->map->all('property');

        $previous = array_values($this->map->all('file'));

        foreach (array_chunk($previous, 1000) as $ids) {
            DB::table('files')->whereIn('id', $ids)->delete();
        }

        DB::connection('legacy')->table('files')->orderBy('id')
            ->chunk(self::CHUNK, function ($files) use ($entryByLegacyId, $people, $properties, &$stats): void {
                DB::transaction(function () use ($files, $entryByLegacyId, $people, $properties, &$stats): void {
                    foreach ($files as $file) {
                        [$type, $id] = $this->morphTarget($file, $entryByLegacyId, $people, $properties);

                        if ($type === null || $id === null) {
                            $stats['skipped_unmapped']++;

                            continue;
                        }

                        $newId = (int) DB::table('files')->insertGetId([
                            'fileable_type' => $type,
                            'fileable_id' => $id,
                            'disk' => $file->disk ?: 'spaces',
                            'path' => $file->filepath,
                            'original_name' => mb_substr((string) $file->filename, 0, 255),
                            'mime_type' => $file->mimetypes !== null ? mb_substr($file->mimetypes, 0, 255) : null,
                            'uploaded_by' => $people[$file->created_by] ?? null,
                            'deleted_at' => $file->deleted_at,
                            'created_at' => $file->created_at,
                            'updated_at' => $file->updated_at,
                        ]);

                        $this->map->remember('file', (int) $file->id, $newId);
                        $stats['imported']++;
                    }
                });
            });

        return $stats;
    }

    /**
     * @param  array<int|string, int|string>  $entryByLegacyId
     * @param  array<int, int>  $people
     * @param  array<int, int>  $properties
     * @return array{class-string|null, int|null}
     */
    private function morphTarget(\stdClass $file, array $entryByLegacyId, array $people, array $properties): array
    {
        return match (true) {
            str_contains($file->fileable_type, 'WorkTimeRecord') => [
                TimeEntry::class,
                isset($entryByLegacyId[$file->fileable_id]) ? (int) $entryByLegacyId[$file->fileable_id] : null,
            ],
            str_contains($file->fileable_type, 'UserModule') => [Person::class, $people[$file->fileable_id] ?? null],
            str_contains($file->fileable_type, 'PropertiesModule') => [Property::class, $properties[$file->fileable_id] ?? null],
            default => [null, null],
        };
    }
}
