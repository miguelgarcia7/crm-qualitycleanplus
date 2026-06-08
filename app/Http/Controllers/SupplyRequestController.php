<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Enums\SupplyRequestStatus;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Actions\RejectStep;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supply requests (ADR-0012/0014). Requesters create them; Front Desk fulfills
 * and Admins approve new items. Fulfillment + approval drive the underlying
 * workflow steps.
 */
class SupplyRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canFulfill = $user->can('workflows.supply_request.fulfill');
        $canApprove = $user->can('workflows.supply_request.approve_new_item');

        $mine = SupplyRequest::query()
            ->where('requested_by', $user->id)
            ->with(['category:id,name', 'itemVariant.item:id,name', 'beneficiary:id,name'])
            ->latest('id')
            ->get();

        $queue = ($canFulfill || $canApprove)
            ? SupplyRequest::query()
                ->whereIn('status', [SupplyRequestStatus::Pending, SupplyRequestStatus::Approved])
                ->with(['category:id,name', 'itemVariant.item:id,name', 'beneficiary:id,name', 'requestedBy:id,name', 'workflow'])
                ->latest('id')
                ->get()
            : collect();

        return Inertia::render('admin/requests/index', [
            'categories' => Category::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug', 'has_variants']),
            'variants' => ItemVariant::query()->where('active', true)->with('item:id,name,category_id')->get()
                ->map(fn (ItemVariant $v): array => [
                    'id' => $v->id,
                    'category_id' => $v->item->category_id,
                    'label' => $v->item->name.' — '.$v->label(),
                ])->values(),
            'contractors' => Person::query()->where('status', PersonStatus::ContractorActive)->orderBy('name')->get(['id', 'name']),
            'mine' => $mine->map(fn (SupplyRequest $r): array => $this->summary($r)),
            'queue' => $queue->map(fn (SupplyRequest $r): array => $this->summary($r, true)),
            'can' => ['initiate' => $user->can('workflows.supply_request.initiate'), 'fulfill' => $canFulfill, 'approve' => $canApprove],
        ]);
    }

    public function store(Request $request, StartWorkflow $start): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.supply_request.initiate'), 403);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'beneficiary_type' => ['required', 'in:self,contractor,general_office'],
            'beneficiary_person_id' => ['nullable', 'required_if:beneficiary_type,contractor', 'integer', 'exists:people,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'item_variant_id' => ['nullable', 'integer', 'exists:item_variants,id'],
            'proposed_item_name' => ['nullable', 'string', 'max:255', 'required_without:item_variant_id'],
            'proposed_description' => ['nullable', 'string', 'max:2000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'charge_amount' => ['nullable', 'numeric', 'min:0'],
            'split_payments' => ['nullable', 'integer', 'min:1', 'max:4'],
            'needed_by' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $supplyRequest = SupplyRequest::create([
            'category_id' => $validated['category_id'],
            'item_variant_id' => $validated['item_variant_id'] ?? null,
            'beneficiary_type' => $validated['beneficiary_type'],
            'beneficiary_person_id' => $validated['beneficiary_person_id'] ?? null,
            'quantity' => $validated['quantity'],
            'charge_amount' => isset($validated['charge_amount']) ? (int) round((float) $validated['charge_amount'] * 100) : null,
            'split_payments' => $validated['split_payments'] ?? null,
            'needed_by' => $validated['needed_by'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'proposed_item_name' => $validated['proposed_item_name'] ?? null,
            'proposed_description' => $validated['proposed_description'] ?? null,
            'estimated_cost' => isset($validated['estimated_cost']) ? (int) round((float) $validated['estimated_cost'] * 100) : null,
            'requested_by' => $request->user()->id,
            'status' => SupplyRequestStatus::Pending,
        ]);

        $workflow = $start->handle(WorkflowType::SupplyRequest, $supplyRequest, $request->user());
        $supplyRequest->update(['workflow_id' => $workflow->id]);

        return back()->with('success', 'Request submitted.');
    }

    public function approve(Request $request, SupplyRequest $supplyRequest, CompleteStep $action): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.supply_request.approve_new_item'), 403);

        $action->handle($this->currentStep($supplyRequest, 'approve_new_item'), $request->user());

        return back()->with('success', 'New item approved.');
    }

    public function deny(Request $request, SupplyRequest $supplyRequest, RejectStep $action): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.supply_request.approve_new_item'), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $action->handle($this->currentStep($supplyRequest, 'approve_new_item'), $request->user(), $validated['reason']);

        return back()->with('success', 'Request denied.');
    }

    public function fulfill(Request $request, SupplyRequest $supplyRequest, CompleteStep $action): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.supply_request.fulfill'), 403);

        $validated = $request->validate([
            'item_variant_id' => ['nullable', 'integer', 'exists:item_variants,id'],
        ]);

        if (isset($validated['item_variant_id'])) {
            $supplyRequest->update(['item_variant_id' => (int) $validated['item_variant_id']]);
            $supplyRequest->refresh();
        }

        $action->handle($this->currentStep($supplyRequest, 'fulfill'), $request->user());

        return back()->with('success', 'Request fulfilled.');
    }

    /** The workflow's current pending step, asserting it is the expected one. */
    private function currentStep(SupplyRequest $supplyRequest, string $expectedKey): WorkflowStep
    {
        $step = $supplyRequest->workflow?->currentStep();

        if ($step === null || $step->step_key !== $expectedKey) {
            throw ValidationException::withMessages(['request' => 'This request is not awaiting that action.']);
        }

        return $step;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SupplyRequest $r, bool $forQueue = false): array
    {
        return [
            'id' => $r->id,
            'category' => $r->category->name,
            'item' => $r->itemVariant?->item->name.($r->itemVariant ? ' — '.$r->itemVariant->label() : ($r->proposed_item_name ? $r->proposed_item_name.' (new)' : '—')),
            'beneficiary' => $r->beneficiary_person_id !== null ? $r->beneficiary->name : $r->beneficiary_type->label(),
            'quantity' => $r->quantity,
            'charge_amount' => $r->charge_amount,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'is_new_item' => $r->isNewItem(),
            'requested_by' => $forQueue ? $r->requestedBy?->name : null,
            'step_key' => $forQueue ? $r->workflow?->currentStep()?->step_key : null,
        ];
    }
}
