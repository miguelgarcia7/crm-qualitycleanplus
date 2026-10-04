<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Workflows\Actions\CancelWorkflow;
use App\Domain\Workflows\Actions\RejectStep;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruiter-facing more-staff queue (ADR-0021): open staffing requests for the
 * recruiter's properties, sorted by urgency, plus the decided ones (History).
 * Recruiters fulfill a request with "Place contractor" — a new work order
 * prefilled and linked to it (WorkOrderController::create) — or decline here;
 * super-admins can cancel.
 */
class MoreStaffController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('workflows.more_staff.fulfill'), 403);

        $propertyIds = $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES) ? null : $user->assignedProperties()->pluck('properties.id')->all();
        $query = fn (array $statuses) => MoreStaffRequest::query()
            ->whereIn('status', $statuses)
            ->when($propertyIds !== null, fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->with(['property:id,name', 'position:id,name', 'initiatedBy:id,name', 'workOrders.person:id,name', 'workOrders.createdBy:id,name']);

        $open = $query([MoreStaffStatus::Submitted, MoreStaffStatus::InProgress])->get()
            ->sortByDesc(fn (MoreStaffRequest $r): array => [$r->urgency->weight(), -$r->by_date->getTimestamp()])
            ->values()
            ->map(fn (MoreStaffRequest $r): array => $this->row($r));

        $history = $query([MoreStaffStatus::Fulfilled, MoreStaffStatus::Declined, MoreStaffStatus::Cancelled])
            ->latest('updated_at')
            ->limit(200)
            ->get();
        $deciders = Person::query()
            ->whereIn('id', $history->flatMap(fn (MoreStaffRequest $r) => [$r->declined_by, $r->cancelled_by])->filter()->unique())
            ->pluck('name', 'id');

        return Inertia::render('admin/more-staff/index', [
            'requests' => $open,
            'history' => $history->map(fn (MoreStaffRequest $r): array => [...$this->row($r), ...$this->decision($r, $deciders)])->values(),
            'can' => [
                'place' => $user->can('create', WorkOrder::class),
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

    /**
     * One request as the queue shows it: what, where, when, why, the PM's
     * notes, and who has been placed so far.
     *
     * @return array<string, mixed>
     */
    private function row(MoreStaffRequest $r): array
    {
        $today = now()->startOfDay();

        return [
            'id' => $r->id,
            'property' => $r->property?->name,
            'position' => $r->position?->name,
            'quantity_requested' => $r->quantity_requested,
            'quantity_fulfilled' => $r->quantity_fulfilled,
            'by_date' => $r->by_date->format('D M j'),
            'days_left' => (int) $today->diffInDays($r->by_date->copy()->startOfDay(), false),
            'urgency' => $r->urgency->value,
            'urgency_label' => $r->urgency->label(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'reason' => $r->reason,
            'notes' => $r->notes,
            'requested_by' => $r->initiatedBy?->name,
            'requested_at' => $r->created_at?->format('M j'),
            'is_overdue' => $r->isOverdue(),
            'placed' => $r->workOrders->sortBy('id')->map(fn ($wo) => $wo->person?->name)->filter()->values()->all(),
        ];
    }

    /**
     * Who closed a decided request, when, and why.
     *
     * @param  Collection<int, string>  $deciders  person id => name
     * @return array{decided_by: string|null, decided_at: string|null, note: string|null}
     */
    private function decision(MoreStaffRequest $r, Collection $deciders): array
    {
        return match ($r->status) {
            // Fulfilled by the placements themselves: the last one's recruiter
            // closed it out.
            MoreStaffStatus::Fulfilled => [
                'decided_by' => $r->workOrders->sortBy('id')->last()?->createdBy?->name,
                'decided_at' => $r->fulfilled_at?->format('M j, Y'),
                'note' => null,
            ],
            MoreStaffStatus::Declined => [
                'decided_by' => $deciders->get($r->declined_by),
                'decided_at' => $r->declined_at?->format('M j, Y'),
                'note' => $r->decline_reason,
            ],
            default => [
                'decided_by' => $deciders->get($r->cancelled_by),
                'decided_at' => $r->cancelled_at?->format('M j, Y'),
                'note' => $r->cancel_reason,
            ],
        };
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
