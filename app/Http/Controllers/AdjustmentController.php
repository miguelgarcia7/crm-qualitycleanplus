<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Actions\CreateManualAdjustment;
use App\Domain\Adjustments\Actions\DeleteAdjustment;
use App\Domain\Adjustments\Models\AdjustmentItem;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Manual payroll adjustments (incentives/deductions) on a payroll period. Scoped
 * to the property "(own)" rule, like the time grid. Every manual adjustment is
 * picked from Billing → Adjustment Items: the item sets the type and whether
 * the hotel is billed; the recruiter sets the amount and an optional note.
 */
class AdjustmentController extends Controller
{
    public function store(Request $request, PayrollPeriod $period, CreateManualAdjustment $action): RedirectResponse
    {
        $this->authorizeProperty($period->property, 'time_entries.add_adjustment');

        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'adjustment_item_id' => ['required', 'integer', Rule::exists('adjustment_items', 'id')->where('active', true)->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'adjustment_item_id.required' => 'Pick an item from the list.',
            'adjustment_item_id.exists' => 'That item is no longer on the list. Pick another.',
        ]);

        $item = AdjustmentItem::findOrFail($validated['adjustment_item_id']);

        $action->handle($period, [
            'person_id' => (int) $validated['person_id'],
            'work_order_id' => isset($validated['work_order_id']) ? (int) $validated['work_order_id'] : null,
            'adjustment_item_id' => $item->id,
            'value' => (int) round((float) $validated['amount'] * 100),
            'type' => $item->type->value,
            'is_billable' => $item->is_billable,
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
