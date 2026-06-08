<?php

namespace App\Domain\Imports\Models;

use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\People\Models\Person;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Factories\ImportBatchRowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One parsed line of an import file (Phase 05, 40-flows/import-hours.md).
 *
 * @property ImportRowStatus $status
 * @property array<string, mixed> $raw_data
 */
class ImportBatchRow extends Model
{
    /** @use HasFactory<ImportBatchRowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'raw_data',
        'matched_person_id',
        'existing_wo_id',
        'status',
        'resolution',
        'resulting_work_order_id',
        'resulting_time_entry_id',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportRowStatus::class,
            'raw_data' => 'array',
            'row_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function matchedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'matched_person_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function existingWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'existing_wo_id');
    }

    /**
     * @return BelongsTo<TimeEntry, $this>
     */
    public function resultingTimeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class, 'resulting_time_entry_id');
    }

    /**
     * Rows that will produce a time entry on commit (not skipped / errored).
     *
     * @param  Builder<ImportBatchRow>  $query
     */
    public function scopeCommittable(Builder $query): void
    {
        $query->whereIn('status', [
            ImportRowStatus::Matched->value,
            ImportRowStatus::RateConflict->value,
            ImportRowStatus::NeedsWoCreation->value,
        ]);
    }
}
