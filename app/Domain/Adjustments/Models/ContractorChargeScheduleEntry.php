<?php

namespace App\Domain\Adjustments\Models;

use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payroll-period slice of a contractor charge schedule (ADR-0014).
 *
 * @property ChargeEntryStatus $status
 */
class ContractorChargeScheduleEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'schedule_id',
        'payroll_period_id',
        'amount',
        'payment_index',
        'status',
        'applied_adjustment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChargeEntryStatus::class,
            'amount' => 'integer',
            'payment_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ContractorChargeSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ContractorChargeSchedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }
}
