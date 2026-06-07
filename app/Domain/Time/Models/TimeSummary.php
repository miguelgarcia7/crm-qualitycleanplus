<?php

namespace App\Domain\Time\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Factories\TimeSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Materialized per-(work_order, week) rollup (ADR-0008). Written only by
 * RecomputeTimeSummary — never edited by hand.
 *
 * @property CarbonImmutable $week_start
 * @property CarbonImmutable $week_end
 * @property int $regular_minutes
 * @property int $overtime_minutes
 * @property int $holiday_minutes
 * @property int $training_minutes
 * @property int $regular_amount_pay
 * @property int $overtime_amount_pay
 * @property int $holiday_amount_pay
 * @property int $training_amount_pay
 * @property int $regular_amount_bill
 * @property int $overtime_amount_bill
 * @property int $holiday_amount_bill
 * @property int $training_amount_bill
 * @property int $total_pay
 * @property int $total_bill
 */
class TimeSummary extends Model
{
    /** @use HasFactory<TimeSummaryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'regular_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'holiday_minutes' => 'integer',
            'training_minutes' => 'integer',
            'regular_amount_pay' => 'integer',
            'overtime_amount_pay' => 'integer',
            'holiday_amount_pay' => 'integer',
            'training_amount_pay' => 'integer',
            'regular_amount_bill' => 'integer',
            'overtime_amount_bill' => 'integer',
            'holiday_amount_bill' => 'integer',
            'training_amount_bill' => 'integer',
            'total_pay' => 'integer',
            'total_bill' => 'integer',
            'last_recomputed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
