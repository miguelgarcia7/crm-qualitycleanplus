<?php

namespace App\Http\Controllers;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Pto\Actions\AdjustPtoBalance;
use App\Domain\Pto\Actions\ApprovePtoRequest;
use App\Domain\Pto\Actions\CancelPtoRequest;
use App\Domain\Pto\Actions\EnsurePtoYear;
use App\Domain\Pto\Actions\RejectPtoRequest;
use App\Domain\Pto\Actions\SubmitPtoRequest;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Pto\Models\PtoYearAllotment;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PTO "Time Off" surface (ADR-0016): a staff member's balances + requests, the
 * approver queue, and (for `pto.balances.view_all`/`adjust_manual`) others' balances
 * and manual adjustments.
 */
class PtoController extends Controller
{
    public function index(Request $request, EnsurePtoYear $ensureYear): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('pto.balances.view_own'), 403);

        $myBalances = null;
        $noticePeriod = false;
        if ($user->status->isStaff() && $user->hire_date !== null) {
            $myBalances = $this->balancePayload($ensureYear->handle($user));
            $noticePeriod = $this->hasActiveTermination($user);
        }

        $canApprove = $user->can('workflows.pto.approve');
        $canViewAll = $user->can('pto.balances.view_all');

        return Inertia::render('admin/pto/index', [
            'myBalances' => $myBalances,
            'noticePeriod' => $noticePeriod,
            'myRequests' => PtoRequest::query()->where('person_id', $user->id)
                ->latest('id')->limit(50)->get()->map(fn (PtoRequest $r): array => $this->requestPayload($r)),
            'queue' => $canApprove ? $this->approvalQueue($user) : [],
            'others' => $canViewAll ? $this->othersBalances() : [],
            'can' => [
                'submit' => $user->can('workflows.pto.initiate') && $user->status->isStaff(),
                'approve' => $canApprove,
                'viewAll' => $canViewAll,
                'adjust' => $user->can('pto.balances.adjust_manual'),
            ],
        ]);
    }

    public function store(Request $request, SubmitPtoRequest $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('workflows.pto.initiate'), 403);

        $validated = $request->validate([
            'bucket' => ['required', Rule::enum(PtoBucket::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notice_period_warning_acknowledged' => ['nullable', 'boolean'],
        ]);

        $action->handle($user, $validated);

        return back()->with('success', 'PTO request submitted.');
    }

    public function approve(Request $request, PtoRequest $ptoRequest, ApprovePtoRequest $action): RedirectResponse
    {
        $this->authorize('approve', $ptoRequest);
        $action->handle($ptoRequest, $request->user());

        return back()->with('success', 'PTO request approved.');
    }

    public function reject(Request $request, PtoRequest $ptoRequest, RejectPtoRequest $action): RedirectResponse
    {
        $this->authorize('reject', $ptoRequest);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $action->handle($ptoRequest, $request->user(), $validated['reason']);

        return back()->with('success', 'PTO request rejected.');
    }

    public function cancel(Request $request, PtoRequest $ptoRequest, CancelPtoRequest $action): RedirectResponse
    {
        $this->authorize('cancel', $ptoRequest);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $action->handle($ptoRequest, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'PTO request cancelled.');
    }

    public function adjust(Request $request, AdjustPtoBalance $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('pto.balances.adjust_manual'), 403);

        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'vacation_hours' => ['nullable', 'numeric'],
            'scheduled_hours' => ['nullable', 'numeric'],
            'unscheduled_hours' => ['nullable', 'numeric'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $action->handle(Person::findOrFail($validated['person_id']), $validated, $user);

        return back()->with('success', 'Balance adjusted.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function approvalQueue(Person $approver): array
    {
        return PtoRequest::query()->pending()
            ->with('person:id,name')
            ->latest('id')->get()
            ->filter(fn (PtoRequest $r): bool => $approver->can('approve', $r))
            ->map(fn (PtoRequest $r): array => $this->requestPayload($r, withPerson: true))
            ->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function othersBalances(): array
    {
        return Person::query()
            ->whereIn('status', [PersonStatus::StaffActive->value, PersonStatus::StaffInactive->value])
            ->orderBy('name')->get()
            ->map(function (Person $p): array {
                $allotment = PtoYearAllotment::query()->where('person_id', $p->id)
                    ->where('status', 'open')->latest('year_start')->first();

                return [
                    'person_id' => $p->id,
                    'name' => $p->name,
                    'balances' => $allotment === null ? null : $this->balancePayload($allotment),
                ];
            })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function balancePayload(PtoYearAllotment $allotment): array
    {
        return collect(PtoBucket::cases())->map(fn (PtoBucket $b): array => [
            'bucket' => $b->value,
            'label' => $b->label(),
            'available' => $allotment->availableFor($b),
            'pending' => $allotment->pendingFor($b),
            'allotted' => $allotment->allotmentFor($b),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(PtoRequest $r, bool $withPerson = false): array
    {
        return array_filter([
            'id' => $r->id,
            'person' => $withPerson ? $r->person?->name : null,
            'bucket' => $r->bucket->label(),
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'hours' => (float) $r->hours,
            'status' => $r->status->value,
            'reason' => $r->reason,
            'cancellable' => in_array($r->status, [PtoRequestStatus::Pending, PtoRequestStatus::Approved], true),
        ], fn ($v) => $v !== null);
    }

    private function hasActiveTermination(Person $person): bool
    {
        return Workflow::query()
            ->where('type', WorkflowType::Termination->value)
            ->where('status', WorkflowStatus::InProgress->value)
            ->where('subject_type', $person->getMorphClass())
            ->where('subject_id', $person->id)
            ->exists();
    }
}
