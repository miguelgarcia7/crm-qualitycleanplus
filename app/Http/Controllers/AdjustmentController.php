<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Actions\CreateManualAdjustment;
use App\Domain\Adjustments\Actions\DeleteAdjustment;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manual payroll adjustments (incentives/deductions) on a payroll period. Scoped
 * to the property "(own)" rule, like the time grid.
 */
class AdjustmentController extends Controller
{
    public function store(Request $request, PayrollPeriod $period, CreateManualAdjustment $action): RedirectResponse
    {
        $this->authorizeProperty($period->property, 'time_entries.add_adjustment');

        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:incentive,deduction'],
            'is_billable' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle($period, [
            'person_id' => (int) $validated['person_id'],
            'work_order_id' => isset($validated['work_order_id']) ? (int) $validated['work_order_id'] : null,
            'value' => (int) round((float) $validated['amount'] * 100),
            'type' => $validated['type'],
            'is_billable' => (bool) ($validated['is_billable'] ?? false),
            'notes' => $validated['notes'] ?? null,
        ], $request->user());

        return back()->with('success', 'Adjustment added.');
    }

    public function destroy(TimeEntryAdjustment $adjustment, DeleteAdjustment $action): RedirectResponse
    {
        $this->authorizeProperty($adjustment->payrollPeriod->property, 'time_entries.add_adjustment');

        $action->handle($adjustment);

        return back()->with('success', 'Adjustment removed.');
    }

    /** Permission + property "(own)" scoping (recruiter/PM see only assigned). */
    private function authorizeProperty(?Property $property, string $permission): void
    {
        $user = Auth::user();
        abort_unless($user instanceof Person && $user->can($permission), 403);
        abort_unless($property !== null, 404);

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        abort_unless($user->isAssignedTo($property), 403);
    }
}
