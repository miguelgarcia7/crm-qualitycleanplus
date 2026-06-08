<?php

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use Illuminate\Validation\ValidationException;

/**
 * Removes a manual adjustment while its payroll period is still open.
 */
class DeleteAdjustment
{
    public function handle(TimeEntryAdjustment $adjustment): void
    {
        if (! $adjustment->payrollPeriod->status->isEditable()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'Adjustments can only be removed while the payroll period is open.',
            ]);
        }

        $adjustment->delete();
    }
}
