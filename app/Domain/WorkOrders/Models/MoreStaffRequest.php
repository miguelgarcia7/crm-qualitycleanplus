<?php

namespace App\Domain\WorkOrders\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\MoreStaffUrgency;
use Carbon\CarbonImmutable;
use Database\Factories\MoreStaffRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A property manager's request for more contractors (ADR-0021). The row is the
 * record of truth for the request; fulfillment is tracked by the work orders
 * linked back via `more_staff_request_id`.
 *
 * @property MoreStaffStatus $status
 * @property MoreStaffUrgency $urgency
 * @property CarbonImmutable $by_date
 * @property CarbonImmutable|null $fulfilled_at
 */
class MoreStaffRequest extends Model
{
    /** @use HasFactory<MoreStaffRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'property_id',
        'position_id',
        'quantity_requested',
        'quantity_fulfilled',
        'by_date',
        'urgency',
        'reason',
        'notes',
        'status',
        'initiated_by',
        'assigned_recruiter_id',
        'declined_at',
        'declined_by',
        'decline_reason',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'fulfilled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MoreStaffStatus::class,
            'urgency' => MoreStaffUrgency::class,
            'quantity_requested' => 'integer',
            'quantity_fulfilled' => 'integer',
            'by_date' => 'date',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fulfilled_at' => 'datetime',
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
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function assignedRecruiter(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_recruiter_id');
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Work orders placed against this request.
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** Past its by-date and still open. */
    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->by_date->isPast();
    }

    /** Placements still needed to fully satisfy the request. */
    public function remaining(): int
    {
        return max(0, $this->quantity_requested - $this->quantity_fulfilled);
    }
}
