<?php

namespace App\Http\Controllers\Minute;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Workflows\Actions\CancelWorkflow;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\MoreStaffUrgency;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PM-facing more-staff requests on QC Minute (ADR-0021). A PM asks for more
 * contractors at one of their assigned properties (one position per request);
 * the assigned recruiter fulfills or declines in the back office.
 */
class MoreStaffController extends Controller
{
    public function index(Request $request): Response
    {
        $pm = $request->user();

        $properties = Property::query()
            ->whereIn('id', $pm->assignedProperties()->pluck('properties.id')->all())
            ->with(['positionRates' => fn ($q) => $q->where('is_active', true), 'positionRates.position:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Property $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'positions' => $p->positionRates
                    ->map(fn ($r): ?array => $r->position === null ? null : ['id' => $r->position->id, 'name' => $r->position->name])
                    ->filter()->unique('id')->values()->all(),
            ]);

        $requests = MoreStaffRequest::query()
            ->where('initiated_by', $pm->id)
            ->with(['property:id,name', 'position:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (MoreStaffRequest $r): array => [
                'id' => $r->id,
                'property' => $r->property?->name,
                'position' => $r->position?->name,
                'quantity_requested' => $r->quantity_requested,
                'quantity_fulfilled' => $r->quantity_fulfilled,
                'by_date' => $r->by_date->toDateString(),
                'urgency' => $r->urgency->value,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'is_overdue' => $r->isOverdue(),
            ]);

        return Inertia::render('minute/more-staff/index', [
            'properties' => $properties,
            'requests' => $requests,
            'urgencies' => array_map(fn (MoreStaffUrgency $u): array => ['value' => $u->value, 'label' => $u->label()], MoreStaffUrgency::cases()),
            'can' => ['initiate' => $pm->can('workflows.more_staff.initiate')],
        ]);
    }

    public function store(Request $request, StartWorkflow $start): RedirectResponse
    {
        $pm = $request->user();
        abort_unless($pm instanceof Person && $pm->can('workflows.more_staff.initiate'), 403);

        $validated = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'by_date' => ['required', 'date', 'after_or_equal:today'],
            'urgency' => ['required', 'in:'.implode(',', array_column(MoreStaffUrgency::cases(), 'value'))],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $property = Property::findOrFail($validated['property_id']);
        abort_unless($pm->assignedProperties()->where('properties.id', $property->id)->exists(), 403);

        $recruiterId = $property->assignments()
            ->where('role', PropertyAssignmentRole::Recruiter->value)
            ->value('person_id');

        $moreStaffRequest = MoreStaffRequest::create([
            'property_id' => $property->id,
            'position_id' => $validated['position_id'],
            'quantity_requested' => $validated['quantity'],
            'by_date' => $validated['by_date'],
            'urgency' => $validated['urgency'],
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'status' => MoreStaffStatus::Submitted,
            'initiated_by' => $pm->id,
            'assigned_recruiter_id' => $recruiterId,
        ]);

        $workflow = $start->handle(WorkflowType::MoreStaff, $moreStaffRequest, $pm);
        $moreStaffRequest->update(['workflow_id' => $workflow->id]);

        return back()->with('success', 'Staffing request submitted.');
    }

    public function cancel(Request $request, MoreStaffRequest $moreStaffRequest, CancelWorkflow $action): RedirectResponse
    {
        $pm = $request->user();
        abort_unless($pm instanceof Person && $pm->can('workflows.more_staff.cancel_own'), 403);
        abort_unless($moreStaffRequest->initiated_by === $pm->id, 403);

        if (! $moreStaffRequest->status->isOpen()) {
            throw ValidationException::withMessages(['request' => 'This request is no longer open.']);
        }

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $workflow = $moreStaffRequest->workflow;
        abort_if($workflow === null, 404);

        $action->handle($workflow, $pm, $validated['reason']);

        return back()->with('success', 'Staffing request cancelled.');
    }
}
