<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Workflows\Actions\CancelWorkflow;
use App\Domain\Workflows\Actions\RejectStep;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruiter-facing more-staff queue (ADR-0021): open staffing requests for the
 * recruiter's properties, sorted by urgency. Recruiters fulfill by linking work
 * orders (handled on WO create) or decline here; super-admins can cancel.
 */
class MoreStaffController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('workflows.more_staff.fulfill'), 403);

        $query = MoreStaffRequest::query()
            ->whereIn('status', [MoreStaffStatus::Submitted, MoreStaffStatus::InProgress])
            ->with(['property:id,name', 'position:id,name', 'initiatedBy:id,name']);

        if (! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all());
        }

        $rows = $query->get()
            ->sortByDesc(fn (MoreStaffRequest $r): array => [$r->urgency->weight(), -$r->by_date->getTimestamp()])
            ->values()
            ->map(fn (MoreStaffRequest $r): array => [
                'id' => $r->id,
                'property' => $r->property?->name,
                'position' => $r->position?->name,
                'quantity_requested' => $r->quantity_requested,
                'quantity_fulfilled' => $r->quantity_fulfilled,
                'by_date' => $r->by_date->toDateString(),
                'urgency' => $r->urgency->value,
                'status' => $r->status->value,
                'reason' => $r->reason,
                'requested_by' => $r->initiatedBy?->name,
                'is_overdue' => $r->isOverdue(),
            ]);

        return Inertia::render('admin/more-staff/index', [
            'requests' => $rows,
            'can' => [
                'decline' => $user->can('workflows.more_staff.decline'),
                'cancel' => $user->can('workflows.more_staff.cancel_others'),
            ],
        ]);
    }

    public function decline(Request $request, MoreStaffRequest $moreStaffRequest, RejectStep $action): RedirectResponse
    {
        $this->authorizeProperty($moreStaffRequest, 'workflows.more_staff.decline');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->handle($this->fulfillStep($moreStaffRequest), $request->user(), $validated['reason']);

        return back()->with('success', 'Staffing request declined.');
    }

    public function cancel(Request $request, MoreStaffRequest $moreStaffRequest, CancelWorkflow $action): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.more_staff.cancel_others'), 403);

        if (! $moreStaffRequest->status->isOpen()) {
            throw ValidationException::withMessages(['request' => 'This request is no longer open.']);
        }

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $workflow = $moreStaffRequest->workflow;
        abort_if($workflow === null, 404);

        $action->handle($workflow, $request->user(), $validated['reason']);

        return back()->with('success', 'Staffing request cancelled.');
    }

    private function fulfillStep(MoreStaffRequest $moreStaffRequest): WorkflowStep
    {
        $step = $moreStaffRequest->workflow?->currentStep();

        if ($step === null || $step->step_key !== 'fulfill_staffing') {
            throw ValidationException::withMessages(['request' => 'This request is not awaiting fulfillment.']);
        }

        return $step;
    }

    private function authorizeProperty(MoreStaffRequest $moreStaffRequest, string $permission): void
    {
        $user = Auth::user();
        abort_unless($user instanceof Person && $user->can($permission), 403);

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        abort_unless(
            $user->assignedProperties()->where('properties.id', $moreStaffRequest->property_id)->exists(),
            403,
        );
    }
}
