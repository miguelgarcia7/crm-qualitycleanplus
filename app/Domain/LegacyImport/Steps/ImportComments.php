<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use App\Domain\People\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legacy time-record comments → payroll activity history (decision #8,
 * phase-final-cutover.md).
 *
 * Each comment becomes an activity_log row in the `payroll` log against the
 * CONTRACTOR — the same shape LogsPayrollActivity uses — so it shows on the
 * profile History tab and survives any later correction of the entry itself.
 * Rows are inserted directly because the causer and created_at are historical.
 */
class ImportComments
{
    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $stats = ['imported' => 0, 'skipped_unmapped' => 0];

        $legacy = DB::connection('legacy');

        $comments = $legacy->table('comments')
            ->join('work_time_records', 'work_time_records.id', '=', 'comments.work_time_record_id')
            ->orderBy('comments.id')
            ->get([
                'comments.id', 'comments.content', 'comments.user_id', 'comments.created_at', 'comments.updated_at',
                'comments.work_time_record_id',
                'work_time_records.contractor_id', 'work_time_records.property_id', 'work_time_records.start_time',
            ]);

        $propertyNames = DB::table('properties')->pluck('name', 'id');

        DB::transaction(function () use ($comments, $propertyNames, &$stats): void {
            DB::table('activity_log')->where('event', 'legacy_comment')->delete();

            $rows = [];

            foreach ($comments as $comment) {
                $personId = $this->map->newId('person', (int) $comment->contractor_id);
                $propertyId = $this->map->newId('property', (int) $comment->property_id);
                $causerId = $this->map->newId('person', $comment->user_id !== null ? (int) $comment->user_id : null);

                if ($personId === null) {
                    $stats['skipped_unmapped']++;

                    continue;
                }

                $rows[] = [
                    'log_name' => 'payroll',
                    'description' => (string) $comment->content,
                    'subject_type' => Person::class,
                    'subject_id' => $personId,
                    'causer_type' => $causerId !== null ? Person::class : null,
                    'causer_id' => $causerId,
                    'event' => 'legacy_comment',
                    'properties' => json_encode([
                        'legacy_comment_id' => (int) $comment->id,
                        'legacy_work_time_record_id' => (int) $comment->work_time_record_id,
                        'entry_date' => Carbon::parse($comment->start_time)->toDateString(),
                        'property' => $propertyId !== null ? $propertyNames[$propertyId] ?? null : null,
                        'property_id' => $propertyId,
                    ]),
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                ];
                $stats['imported']++;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('activity_log')->insert($chunk);
            }
        });

        return $stats;
    }
}
