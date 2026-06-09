<?php

namespace App\Domain\Pto\Models;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoAllotmentStatus;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PtoYearAllotmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A W-2 employee's hire-anniversary PTO year (ADR-0016). Tracks the per-bucket
 * allotment; `available = allotment − reserved (pending + approved)`.
 *
 * @property PtoAllotmentStatus $status
 * @property CarbonImmutable $year_start
 * @property CarbonImmutable $year_end
 */
class PtoYearAllotment extends Model
{
    /** @use HasFactory<PtoYearAllotmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'year_start',
        'year_end',
        'tier_at_year_start',
        'vacation_allotment',
        'scheduled_allotment',
        'unscheduled_allotment',
        'status',
        'closed_at',
        'forfeited_hours_at_close',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PtoAllotmentStatus::class,
            'year_start' => 'date',
            'year_end' => 'date',
            'vacation_allotment' => 'decimal:2',
            'scheduled_allotment' => 'decimal:2',
            'unscheduled_allotment' => 'decimal:2',
            'closed_at' => 'datetime',
            'forfeited_hours_at_close' => 'array',
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
     * @return HasMany<PtoRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PtoRequest::class, 'year_allotment_id');
    }

    /**
     * @return HasMany<PtoGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(PtoGrant::class, 'year_allotment_id');
    }

    public function allotmentFor(PtoBucket $bucket): float
    {
        return (float) $this->{$bucket->allotmentColumn()};
    }

    /** Hours reserved (pending + approved) for a bucket. */
    public function reservedFor(PtoBucket $bucket): float
    {
        return (float) $this->requests()
            ->where('bucket', $bucket->value)
            ->whereIn('status', [PtoRequestStatus::Pending->value, PtoRequestStatus::Approved->value])
            ->sum('hours');
    }

    /** Hours still pending approval for a bucket (for UI display). */
    public function pendingFor(PtoBucket $bucket): float
    {
        return (float) $this->requests()
            ->where('bucket', $bucket->value)
            ->where('status', PtoRequestStatus::Pending->value)
            ->sum('hours');
    }

    public function availableFor(PtoBucket $bucket): float
    {
        return $this->allotmentFor($bucket) - $this->reservedFor($bucket);
    }
}
