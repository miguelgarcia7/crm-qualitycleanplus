<?php

namespace App\Domain\People\Models;

use App\Domain\People\Enums\ReasonCategory;
use App\Domain\People\Enums\TerminationType;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Models\Workflow;
use Carbon\CarbonImmutable;
use Database\Factories\TerminationRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The durable record of a termination (ADR-0018), one per `termination`
 * workflow. Built when the workflow starts; the physical-tasks and
 * final-paycheck steps stamp their outcomes onto it.
 *
 * @property TerminationType $termination_type
 * @property ReasonCategory $reason_category
 * @property CarbonImmutable $effective_date
 * @property CarbonImmutable|null $terminated_at
 * @property CarbonImmutable|null $file_moved_at
 * @property CarbonImmutable|null $final_paycheck_processed_at
 * @property CarbonImmutable|null $quickbooks_removed_at
 * @property CarbonImmutable|null $cancelled_at
 */
class TerminationRecord extends Model
{
    /** @use HasFactory<TerminationRecordFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'person_id',
        'initiated_by',
        'effective_date',
        'termination_type',
        'reason_category',
        'notes',
        'rehireable',
        'file_moved_at',
        'file_moved_by',
        'terminated_at',
        'final_paycheck_period_id',
        'final_paycheck_processed_at',
        'final_paycheck_processed_by',
        'final_paycheck_consolidated_cents',
        'final_paycheck_remainder_cents',
        'quickbooks_removed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'termination_type' => TerminationType::class,
            'reason_category' => ReasonCategory::class,
            'effective_date' => 'date',
            'rehireable' => 'boolean',
            'file_moved_at' => 'datetime',
            'terminated_at' => 'datetime',
            'final_paycheck_processed_at' => 'datetime',
            'final_paycheck_consolidated_cents' => 'integer',
            'final_paycheck_remainder_cents' => 'integer',
            'quickbooks_removed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function finalPaycheckPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'final_paycheck_period_id');
    }
}
