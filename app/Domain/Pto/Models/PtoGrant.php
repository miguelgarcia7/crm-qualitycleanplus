<?php

namespace App\Domain\Pto\Models;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoGrantType;
use Carbon\CarbonImmutable;
use Database\Factories\PtoGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An accrual movement on a PTO allotment (ADR-0016): annual refresh, mid-year tier
 * milestone top-up, or manual adjustment. Hours are per-bucket deltas.
 *
 * @property PtoGrantType $grant_type
 * @property CarbonImmutable $effective_date
 */
class PtoGrant extends Model
{
    /** @use HasFactory<PtoGrantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'year_allotment_id',
        'grant_type',
        'vacation_hours',
        'scheduled_hours',
        'unscheduled_hours',
        'reason',
        'effective_date',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grant_type' => PtoGrantType::class,
            'vacation_hours' => 'decimal:2',
            'scheduled_hours' => 'decimal:2',
            'unscheduled_hours' => 'decimal:2',
            'effective_date' => 'date',
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
