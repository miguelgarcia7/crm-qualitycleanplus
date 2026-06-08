<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Database\Factories\TimesheetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weekly approval wrapper for a property's billable activity (ADR-0007).
 *
 * @property TimesheetStatus $status
 */
class Timesheet extends Model
{
    /** @use HasFactory<TimesheetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'payroll_period_id',
        'source',
        'status',
        'sent_for_approval_at',
        'sent_for_approval_by',
        'declined_at',
        'declined_by',
        'decline_reason',
        'decline_category',
        'approved_at',
        'approved_by',
        'invoice_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TimesheetStatus::class,
            'sent_for_approval_at' => 'datetime',
            'declined_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Timesheets awaiting a property manager's decision.
     *
     * @param  Builder<Timesheet>  $query
     */
    public function scopePendingApproval(Builder $query): void
    {
        $query->where('status', TimesheetStatus::PendingApproval->value);
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
}
