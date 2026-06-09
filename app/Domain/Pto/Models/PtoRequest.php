<?php

namespace App\Domain\Pto\Models;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PtoRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PTO request against one bucket within a year allotment (ADR-0016).
 *
 * @property PtoBucket $bucket
 * @property PtoRequestStatus $status
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 */
class PtoRequest extends Model
{
    /** @use HasFactory<PtoRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'year_allotment_id',
        'bucket',
        'start_date',
        'end_date',
        'hours',
        'reason',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'reject_reason',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'is_self_approved',
        'notice_period_warning_acknowledged',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket' => PtoBucket::class,
            'status' => PtoRequestStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'hours' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_self_approved' => 'boolean',
            'notice_period_warning_acknowledged' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<PtoYearAllotment, $this>
     */
    public function allotment(): BelongsTo
    {
        return $this->belongsTo(PtoYearAllotment::class, 'year_allotment_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'approved_by');
    }

    /**
     * @param  Builder<PtoRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', PtoRequestStatus::Pending->value);
    }
}
