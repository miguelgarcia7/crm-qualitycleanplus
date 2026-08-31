<?php

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Enums\ChargeReason;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Creates the hiring-fee deduction when a contractor is taken on — $250 by
 * default, taken $20 at a time out of their pay.
 *
 * Entries are NOT allocated to payroll periods here. A $250 fee at $20 a period
 * needs 13 periods and only a handful are ever open at once (see
 * `qcp.time.payroll_periods_ahead`), so allocating up front would silently
 * schedule a fraction of the fee. {@see AllocateChargeScheduleEntries} tops the
 * schedule up as periods open.
 */
class ScheduleHiringFee
{
    public function handle(Person $person, ?int $totalCents = null, ?int $perPeriodCents = null): ?ContractorChargeSchedule
    {
        // One hiring fee per contractor. A rehire keeps the original — the fee
        // is for taking them on, and they were already taken on once.
        $existing = ContractorChargeSchedule::query()
            ->where('person_id', $person->id)
            ->where('reason', ChargeReason::HiringFee->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $total = $totalCents ?? (int) config('qcp.hiring_fee.amount_cents', 250_00);
        $perPeriod = $perPeriodCents ?? (int) config('qcp.hiring_fee.per_period_cents', 20_00);

        if ($total <= 0 || $perPeriod <= 0) {
            return null;
        }

        // A per-period amount larger than the fee just means one payment.
        $perPeriod = min($perPeriod, $total);

        return DB::transaction(fn (): ContractorChargeSchedule => ContractorChargeSchedule::create([
            'person_id' => $person->id,
            'reason' => ChargeReason::HiringFee,
            'total_amount' => $total,
            'num_payments' => (int) ceil($total / $perPeriod),
            'amount_per_payment' => $perPeriod,
            'status' => ChargeScheduleStatus::Active,
        ]));
    }
}
