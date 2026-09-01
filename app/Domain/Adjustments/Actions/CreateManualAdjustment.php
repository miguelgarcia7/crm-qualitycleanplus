<?php

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\People\Models\Person;
use App\Domain\Time\Concerns\LogsPayrollActivity;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Validation\ValidationException;

/**
 * Records a manual incentive/deduction on an open payroll period. Deductions are
 * forced non-billable (also enforced on the model). Uniform/charge deductions do
 * NOT come through here — they enter via the supply-request chain (ADR-0014).
 */
class CreateManualAdjustment
{
    use LogsPayrollActivity;

    /**
     * @param  array{person_id:int, work_order_id?:int|null, adjustment_item_id?:int|null, value:int, type:string, is_billable?:bool, notes?:string|null}  $data
     */
    public function handle(PayrollPeriod $period, array $data, ?Person $actor): TimeEntryAdjustment
    {
        if (! $period->status->isEditable()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'Adjustments can only be added while the payroll period is open.',
            ]);
        }

        $type = AdjustmentType::from($data['type']);

        $adjustment = TimeEntryAdjustment::create([
            'person_id' => $data['person_id'],
            'work_order_id' => $data['work_order_id'] ?? null,
            'property_id' => $period->property_id,
            'payroll_period_id' => $period->id,
            'adjustment_item_id' => $data['adjustment_item_id'] ?? null,
            'source_type' => AdjustmentSourceType::Manual,
            'value' => $data['value'],
            'type' => $type,
            'is_billable' => $type === AdjustmentType::Incentive ? ($data['is_billable'] ?? false) : false,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);

        $this->logPayroll(
            $adjustment->person,
            'created',
            sprintf('Added a %s %s of %s', $adjustment->is_billable ? 'billable' : 'non-billable', $type->value, $this->money($adjustment->value)),
            [
                'adjustment_id' => $adjustment->id,
                'payroll_period_id' => $period->id,
                'property_id' => $period->property_id,
                'value' => $adjustment->value,
                'type' => $type->value,
                'is_billable' => $adjustment->is_billable,
            ],
            $actor,
        );

        return $adjustment;
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
