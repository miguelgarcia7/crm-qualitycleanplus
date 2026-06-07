<?php

namespace App\Domain\PropertyBible\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A property QCP services — the root of its Property Bible (Profile, Departments,
 * Positions & Rates, Contracts, History). See 20-domain/property-bible.md.
 *
 * @property PropertyStatus $status
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'pm_name',
        'pm_phone',
        'main_phone',
        'address',
        'city',
        'state',
        'zip',
        'timezone',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'closing_day',
        'tax_rate',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_radius_meters' => 'integer',
            'closing_day' => 'integer',
            'tax_rate' => 'decimal:4',
        ];
    }

    /**
     * @return HasMany<PropertyDepartment, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(PropertyDepartment::class);
    }

    /**
     * @return HasMany<PropertyPositionRate, $this>
     */
    public function positionRates(): HasMany
    {
        return $this->hasMany(PropertyPositionRate::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @return HasMany<PropertyAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PropertyAssignment::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    /**
     * Limit to properties the given person is assigned to (any role).
     *
     * @param  Builder<Property>  $query
     */
    public function scopeAssignedTo(Builder $query, Person $person): void
    {
        $query->whereHas('assignments', function (Builder $assignments) use ($person): void {
            $assignments->where('person_id', $person->getKey());
        });
    }

    /**
     * The current rate for a position: the latest active row whose effective_date
     * is on or before today. Returns null when no rate has been set yet.
     */
    public function currentRateFor(Position|int $position): ?PropertyPositionRate
    {
        $positionId = $position instanceof Position ? $position->getKey() : $position;

        return $this->positionRates()
            ->where('position_id', $positionId)
            ->where('is_active', true)
            ->whereDate('effective_date', '<=', now())
            ->orderByDesc('effective_date')
            ->first();
    }
}
