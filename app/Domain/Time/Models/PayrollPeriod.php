<?php

namespace App\Domain\Time\Models;

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A property's pay week (ADR-0009).
 *
 * @property PayrollPeriodStatus $status
 * @property CarbonImmutable $week_start
 * @property CarbonImmutable $week_end
 */
class PayrollPeriod extends Model
{
    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'week_start',
        'week_end',
        'status',
        'locked_at',
        'locked_by',
        'invoiced_at',
        'closed_at',
        'closed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollPeriodStatus::class,
            'week_start' => 'date',
            'week_end' => 'date',
            'locked_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
