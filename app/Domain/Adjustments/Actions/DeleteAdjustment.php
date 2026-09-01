<?php

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Time\Concerns\LogsPayrollActivity;
use Illuminate\Validation\ValidationException;

/**
 * Removes a manual adjustment while its payroll period is still open.
 */
class DeleteAdjustment
{
    use LogsPayrollActivity;

    public function handle(TimeEntryAdjustment $adjustment): void
    {
        if (! $adjustment->payrollPeriod->status->isEditable()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'Adjustments can only be removed while the payroll period is open.',
            ]);
        }

        // Captured before the delete — afterwards the row is gone.
        $person = $adjustment->person;
        $context = [
            'adjustment_id' => $adjustment->id,
            'payroll_period_id' => $adjustment->payroll_period_id,
            'property_id' => $adjustment->property_id,
            'value' => $adjustment->value,
            'type' => $adjustment->type->value,
            'is_billable' => $adjustment->is_billable,
        ];
        $description = sprintf('Removed the $%s %s', number_format($adjustment->value / 100, 2), $adjustment->type->value);

        $adjustment->delete();

        $this->logPayroll($person, 'deleted', $description, $context);
    }
}
