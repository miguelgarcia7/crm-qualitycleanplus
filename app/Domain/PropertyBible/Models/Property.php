<?php

namespace App\Domain\PropertyBible\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property PropertyTimeSource $time_source
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
        'time_source',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'time_source' => PropertyTimeSource::class,
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
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<PayrollPeriod, $this>
     */
    public function payrollPeriods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }

    /**
     * ISO day-of-week (1 = Monday … 7 = Sunday) this property's work week ends
     * on — the Bible's "closing day". Unset = Sunday, i.e. a Monday–Sunday week.
     */
    public function weekEndsOnIso(): int
    {
        return $this->closing_day ?? 7;
    }

    /**
     * The first day of this property's work week containing $date. A week ends
     * on `closing_day` and starts the day after (ends Wednesday → starts
     * Thursday). Single source of week math — payroll periods, the grid,
     * clock-in and imports all anchor through here (ADR-0009).
     */
    public function weekStartFor(CarbonInterface|string $date): CarbonImmutable
    {
        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->startOfDay()
            : CarbonImmutable::parse($date, $this->timezone)->startOfDay();

        $startIso = $this->weekEndsOnIso() % 7 + 1;

        return $day->subDays(($day->dayOfWeekIso - $startIso + 7) % 7);
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

    /**
     * Positions this property has a current Bible rate for — the only positions
     * a work order may be created against here.
     *
     * @return Collection<int, Position>
     */
    public function configuredPositions(): Collection
    {
        return Position::query()
            ->where('is_active', true)
            ->whereIn('id', $this->positionRates()
                ->where('is_active', true)
                ->whereDate('effective_date', '<=', now())
                ->select('position_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
