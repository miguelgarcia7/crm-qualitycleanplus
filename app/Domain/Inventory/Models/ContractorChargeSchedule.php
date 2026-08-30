<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\ChargeReason;
use App\Domain\Inventory\Enums\ChargeScheduleStatus;
use App\Domain\People\Models\Person;
use Database\Factories\ContractorChargeScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A contractor charge spread over payroll periods (ADR-0014).
 *
 * @property ChargeScheduleStatus $status
 * @property ChargeReason $reason
 * @property-read int|null $applied_count aggregate from withCount()
 * @property-read int|null $collected_amount aggregate from withSum()
 */
class ContractorChargeSchedule extends Model
{
    /** @use HasFactory<ContractorChargeScheduleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_request_id',
        'person_id',
        'reason',
        'total_amount',
        'num_payments',
        'amount_per_payment',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChargeScheduleStatus::class,
            'reason' => ChargeReason::class,
            'total_amount' => 'integer',
            'num_payments' => 'integer',
            'amount_per_payment' => 'integer',
        ];
    }

    /**
     * @return HasMany<ContractorChargeScheduleEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ContractorChargeScheduleEntry::class, 'schedule_id')->orderBy('payment_index');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** Cents still scheduled (not yet applied). */
    public function remainingAmount(): int
    {
        return (int) $this->entries()->where('status', 'scheduled')->sum('amount');
    }
}
