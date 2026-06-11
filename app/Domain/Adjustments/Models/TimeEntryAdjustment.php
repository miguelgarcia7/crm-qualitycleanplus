<?php

namespace App\Domain\Adjustments\Models;

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\People\Models\Person;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Factories\TimeEntryAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An applied incentive/deduction on a (person, payroll_period). `value` is always
 * positive cents; `type` carries the sign. Deductions are never billable
 * (enforced in {@see booted}). See 20-domain/adjustments.md, ADR-0014.
 *
 * @property AdjustmentType $type
 * @property AdjustmentSourceType $source_type
 */
class TimeEntryAdjustment extends Model
{
    /** @use HasFactory<TimeEntryAdjustmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'work_order_id',
        'property_id',
        'payroll_period_id',
        'adjustment_item_id',
        'source_type',
        'source_id',
        'value',
        'type',
        'is_billable',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AdjustmentType::class,
            'source_type' => AdjustmentSourceType::class,
            'value' => 'integer',
            'is_billable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Invariant: deductions can never be billable (20-domain/adjustments.md).
        static::saving(function (TimeEntryAdjustment $adjustment): void {
            if ($adjustment->type === AdjustmentType::Deduction) {
                $adjustment->is_billable = false;
            }
        });
    }

    /** Signed contribution to net pay (positive incentive, negative deduction). */
    public function signedValue(): int
    {
        return $this->type->sign() * $this->value;
    }

    /**
     * @return BelongsTo<AdjustmentItem, $this>
     */
    public function adjustmentItem(): BelongsTo
    {
        return $this->belongsTo(AdjustmentItem::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
