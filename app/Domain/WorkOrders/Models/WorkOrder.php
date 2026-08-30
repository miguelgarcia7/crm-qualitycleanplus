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
        'direct_hire_threshold_minutes',
        'source',
        'is_temporary_assignment',
        'parent_wo_id',
        'more_staff_request_id',
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
            'direct_hire_threshold_minutes' => 'integer',
            'direct_hire_notified_at' => 'datetime',
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
     * @return BelongsTo<MoreStaffRequest, $this>
     */
    public function moreStaffRequest(): BelongsTo
    {
        return $this->belongsTo(MoreStaffRequest::class);
    }

    /**
     * Fill the direct-hire threshold from the property's contracted value (or
     * the system default) when the caller has not set one. Done here rather
     * than in CreateWorkOrder so imports, temporary assignments and factories
     * are covered by the same rule.
     *
     * Resolved once, at creation: changing a property's value later must not
     * move the goalposts on placements already running.
     */
    protected static function booted(): void
    {
        static::creating(function (WorkOrder $workOrder): void {
            // Ask the attribute bag rather than the accessor: the column is
            // NOT NULL so the cast reports int, and "caller did not set one"
            // is the state we actually need to detect.
            if (array_key_exists('direct_hire_threshold_minutes', $workOrder->getAttributes())) {
                return;
            }

            $property = Property::query()->find($workOrder->getAttributes()['property_id'] ?? null);

            $workOrder->direct_hire_threshold_minutes = $property?->directHireThresholdMinutes()
                ?? Property::defaultDirectHireThresholdMinutes();
        });
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', WorkOrderStatus::Active);
    }
}
