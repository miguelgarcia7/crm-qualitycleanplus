<?php

namespace App\Domain\WorkOrders\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The assignment linking a contractor to a property + position with rates.
 * Authoritative rate source for its time entries (ADR-0005).
 *
 * @property WorkOrderStatus $status
 * @property WorkOrderSource $source
 * @property bool $is_temporary_assignment
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 */
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'property_id',
        'position_id',
        'pay_rate',
        'bill_rate',
        'ot_pay_rate',
        'ot_bill_rate',
        'start_date',
        'end_date',
        'status',
        'probationary_period_minutes',
        'source',
        'is_temporary_assignment',
        'parent_wo_id',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'source' => WorkOrderSource::class,
            'is_temporary_assignment' => 'boolean',
            'pay_rate' => 'integer',
            'bill_rate' => 'integer',
            'ot_pay_rate' => 'integer',
            'ot_bill_rate' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'probationary_period_minutes' => 'integer',
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

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', WorkOrderStatus::Active);
    }
}
