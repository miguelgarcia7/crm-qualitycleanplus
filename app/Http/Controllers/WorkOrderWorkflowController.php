<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Starts the WO-lifecycle workflows that act on an existing work order:
 * permanent transfer and temporary assignment (ADR-0019). Pay increase has its
 * own controllers (two-party approval). Scoped to the WO's property "(own)" rule.
 */
class WorkOrderWorkflowController extends Controller
{
    public function transfer(Request $request, WorkOrder $workOrder, StartWorkflow $start): RedirectResponse
    {
        $this->authorizeWorkflow($workOrder, 'workflows.transfer.initiate');

        $validated = $request->validate([
            'effective_date' => ['required', 'date'],
            'new_property_id' => ['required', 'integer', 'exists:properties,id'],
            'new_position_id' => ['required', 'integer', 'exists:positions,id'],
            'new_recruiter_id' => ['nullable', 'integer', 'exists:people,id'],
            'pay_rate' => ['required', 'numeric', 'min:0'],
            'bill_rate' => ['required', 'numeric', 'min:0'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $start->handle(WorkflowType::Transfer, $workOrder->person, $request->user(), [
            'work_order_id' => $workOrder->id,
            'effective_date' => $validated['effective_date'],
            'new_property_id' => (int) $validated['new_property_id'],
            'new_position_id' => (int) $validated['new_position_id'],
            'new_recruiter_id' => isset($validated['new_recruiter_id']) ? (int) $validated['new_recruiter_id'] : null,
            'pay_rate' => $this->cents($validated['pay_rate']),
            'bill_rate' => $this->cents($validated['bill_rate']),
            'ot_pay_rate' => $this->cents($validated['ot_pay_rate']),
            'ot_bill_rate' => $this->cents($validated['ot_bill_rate']),
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Transfer applied.');
    }

    public function temporaryAssignment(Request $request, WorkOrder $workOrder, StartWorkflow $start): RedirectResponse
    {
        $this->authorizeWorkflow($workOrder, 'workflows.temporary_assignment.initiate');

        $validated = $request->validate([
            'new_property_id' => ['required', 'integer', 'exists:properties,id', 'different:home_property_id'],
            'home_property_id' => ['nullable', 'integer'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'pay_rate' => ['required', 'numeric', 'min:0'],
            'bill_rate' => ['required', 'numeric', 'min:0'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $start->handle(WorkflowType::TemporaryAssignment, $workOrder->person, $request->user(), [
            'home_work_order_id' => $workOrder->id,
            'new_property_id' => (int) $validated['new_property_id'],
            'position_id' => (int) $validated['position_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'pay_rate' => $this->cents($validated['pay_rate']),
            'bill_rate' => $this->cents($validated['bill_rate']),
            'ot_pay_rate' => $this->cents($validated['ot_pay_rate']),
            'ot_bill_rate' => $this->cents($validated['ot_bill_rate']),
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Temporary assignment created.');
    }

    private function cents(mixed $dollars): int
    {
        return (int) round(((float) $dollars) * 100);
    }

    /** Permission + property "(own)" scoping on the work order's property. */
    private function authorizeWorkflow(WorkOrder $workOrder, string $permission): void
    {
        $user = Auth::user();
        abort_unless($user instanceof Person && $user->can($permission), 403);

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        abort_unless($user->isAssignedTo($workOrder->property), 403);
    }
}
