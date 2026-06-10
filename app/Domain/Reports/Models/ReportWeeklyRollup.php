<?php

namespace App\Domain\Reports\Models;

use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Operational rollup: one row per (property, position, week), rebuilt from
 * time_summaries by RefreshWeeklyRollup (ADR-0028). Derived data — never a
 * source of truth.
 *
 * @property CarbonImmutable $week_start
 * @property CarbonImmutable $week_end
 */
class ReportWeeklyRollup extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'position_id',
        'week_start',
        'week_end',
        'regular_minutes',
        'overtime_minutes',
        'holiday_minutes',
        'training_minutes',
        'total_minutes',
        'total_pay',
        'total_bill',
        'contractor_count',
        'last_refreshed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date',
            'week_end' => 'immutable_date',
            'regular_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'holiday_minutes' => 'integer',
            'training_minutes' => 'integer',
            'total_minutes' => 'integer',
            'total_pay' => 'integer',
            'total_bill' => 'integer',
            'contractor_count' => 'integer',
            'last_refreshed_at' => 'datetime',
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
}
