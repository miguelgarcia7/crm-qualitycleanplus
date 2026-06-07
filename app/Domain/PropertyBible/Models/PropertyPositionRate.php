<?php

namespace App\Domain\PropertyBible\Models;

use App\Domain\People\Models\Person;
use Carbon\CarbonImmutable;
use Database\Factories\PropertyPositionRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An effective-dated rate row for a (property, position). Money is stored in
 * cents (BIGINT). Rows are never mutated on a rate change — a new row is added.
 * See 20-domain/property-bible.md §3.
 *
 * @property CarbonImmutable $effective_date
 * @property CarbonImmutable|null $end_date
 */
class PropertyPositionRate extends Model
{
    /** @use HasFactory<PropertyPositionRateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'position_id',
        'pay_rate',
        'bill_rate',
        'ot_pay_rate',
        'ot_bill_rate',
        'effective_date',
        'end_date',
        'is_active',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pay_rate' => 'integer',
            'bill_rate' => 'integer',
            'ot_pay_rate' => 'integer',
            'ot_bill_rate' => 'integer',
            'effective_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
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
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
