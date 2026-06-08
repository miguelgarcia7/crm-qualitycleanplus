<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\BeneficiaryType;
use App\Domain\Inventory\Enums\ChargeEntryStatus;
use App\Domain\Inventory\Enums\ChargeScheduleStatus;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\RecipientType;
use App\Domain\Inventory\Enums\SupplyRequestStatus;
use App\Domain\Inventory\Models\ContractorChargeSchedule;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Models\Person;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fulfills a supply request (ADR-0012, ADR-0014): issues stock, and — by
 * category — creates an equipment assignment or a contractor charge schedule.
 * Idempotent: a request already fulfilled is returned unchanged.
 */
class FulfillSupplyRequest
{
    public function __construct(private RecordStockMovement $record) {}

    public function handle(SupplyRequest $request, ?Person $actor): SupplyRequest
    {
        if ($request->status === SupplyRequestStatus::Fulfilled) {
            return $request;
        }

        return DB::transaction(function () use ($request, $actor): SupplyRequest {
            if ($request->item_variant_id === null) {
                throw ValidationException::withMessages([
                    'item_variant_id' => 'Choose an item/variant before fulfilling this request.',
                ]);
            }

            $variant = $request->itemVariant;
            $isContractor = $request->beneficiary_type === BeneficiaryType::Contractor && $request->beneficiary_person_id !== null;

            $this->record->handle($variant, MovementType::Issuance, -abs($request->quantity), [
                'reason' => 'Supply request #'.$request->id,
                'related_request_id' => $request->id,
                'recipient_person_id' => $request->beneficiary_person_id,
                'recipient_type' => ($isContractor ? RecipientType::Contractor : RecipientType::Unspecified)->value,
            ], $actor);

            $category = $request->category;

            if ($category->isEquipment() && $request->beneficiary_person_id !== null) {
                EquipmentAssignment::create([
                    'item_variant_id' => $variant->id,
                    'assigned_to_person_id' => $request->beneficiary_person_id,
                    'source_request_id' => $request->id,
                    'quantity' => $request->quantity,
                    'assigned_at' => now(),
                    'assigned_by' => $actor?->id,
                ]);
            }

            if ($category->isUniforms() && $isContractor && (int) $request->charge_amount > 0) {
                $this->buildChargeSchedule($request);
            }

            $request->update(['status' => SupplyRequestStatus::Fulfilled]);

            return $request->refresh();
        });
    }

    /** Spread the charge across the next N open payroll periods of the contractor's property. */
    private function buildChargeSchedule(SupplyRequest $request): void
    {
        $total = (int) $request->charge_amount;
        $person = $request->beneficiary;

        $workOrder = $person->workOrders()->where('status', WorkOrderStatus::Active->value)->first();
        if ($workOrder === null) {
            throw ValidationException::withMessages([
                'beneficiary_person_id' => 'The contractor has no active work order to schedule charges against.',
            ]);
        }

        $requestedPayments = $request->split_payments ?? $this->defaultPayments($total);

        $periods = PayrollPeriod::query()
            ->where('property_id', $workOrder->property_id)
            ->where('status', 'open')
            ->orderBy('week_start')
            ->limit($requestedPayments)
            ->get();

        if ($periods->isEmpty()) {
            throw ValidationException::withMessages([
                'charge_amount' => 'No open payroll periods are available to schedule this charge.',
            ]);
        }

        $n = $periods->count();
        $base = intdiv($total, $n);
        $remainder = $total - ($base * $n);

        $schedule = ContractorChargeSchedule::create([
            'source_request_id' => $request->id,
            'person_id' => $person->id,
            'total_amount' => $total,
            'num_payments' => $n,
            'amount_per_payment' => $base,
            'status' => ChargeScheduleStatus::Active,
        ]);

        foreach ($periods as $i => $period) {
            $schedule->entries()->create([
                'payroll_period_id' => $period->id,
                'amount' => $base + ($i < $remainder ? 1 : 0),
                'payment_index' => $i + 1,
                'status' => ChargeEntryStatus::Scheduled,
            ]);
        }
    }

    /** Default split by amount (ADR-0014): ≤$10→1, ≤$50→2, ≤$150→3, else 4. */
    private function defaultPayments(int $cents): int
    {
        return match (true) {
            $cents <= 1000 => 1,
            $cents <= 5000 => 2,
            $cents <= 15000 => 3,
            default => 4,
        };
    }
}
