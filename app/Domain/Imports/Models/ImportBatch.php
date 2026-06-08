<?php

namespace App\Domain\Imports\Models;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single weekly Excel hour-import for an import-only property (Phase 05,
 * 40-flows/import-hours.md). The permanent audit record of an import run.
 *
 * @property ImportBatchStatus $status
 * @property array<string, mixed>|null $pending_adjustments
 * @property array<string, mixed>|null $stats
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable|null $rolled_back_at
 */
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'payroll_period_id',
        'file_name',
        'file_hash',
        'status',
        'pending_adjustments',
        'stats',
        'invoice_id',
        'uploaded_by',
        'applied_at',
        'applied_by',
        'rolled_back_at',
        'rolled_back_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'pending_adjustments' => 'array',
            'stats' => 'array',
            'applied_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'uploaded_by');
    }

    /**
     * @return HasMany<ImportBatchRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class);
    }
}
