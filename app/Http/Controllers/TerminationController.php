<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\ReturnEquipment;
use App\Domain\Inventory\Enums\EquipmentAssignmentStatus;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\People\Actions\ProcessFinalPaycheck;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Enums\ReasonCategory;
use App\Domain\People\Enums\TerminationType;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\TerminationRecord;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Actions\CancelWorkflow;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Concerns\LogsWorkflowActivity;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dedicated Terminations area (ADR-0018). HR/recruiters initiate; front desk
 * and payroll act on the current step from the detail page (these steps also
 * appear in the shared My Tasks inbox). Each action checks the seeded
 * `workflows.termination.*` permission and asserts the workflow's current step.
 */
class TerminationController extends Controller
{
    use LogsWorkflowActivity;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('workflows.termination.initiate') || $request->user()->can('termination_records.view'), 403);

        $records = TerminationRecord::query()
            ->with(['person:id,name', 'initiatedBy:id,name', 'workflow:id,status'])
            ->latest('id')
            ->get()
            ->map(fn (TerminationRecord $r): array => [
                'id' => $r->id,
                'workflow_id' => $r->workflow_id,
                'person' => $r->person->name,
                'effective_date' => $r->effective_date->toDateString(),
                'type' => $r->termination_type->label(),
                'reason' => $r->reason_category->label(),
                'status' => $r->workflow->status->label(),
                'initiated_by' => $r->initiatedBy?->name,
            ]);

        return Inertia::render('admin/terminations/index', [
            'records' => $records,
            'can' => ['initiate' => $request->user()->can('workflows.termination.initiate')],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('workflows.termination.initiate'), 403);

        $people = Person::query()
            ->whereIn('status', [PersonStatus::ContractorActive, PersonStatus::StaffActive])
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (Person $p): array => ['id' => $p->id, 'name' => $p->name, 'status' => $p->status->value]);

        return Inertia::render('admin/terminations/create', [
            'people' => $people,
            'types' => array_map(fn (TerminationType $t): array => ['value' => $t->value, 'label' => $t->label()], TerminationType::cases()),
            'reasons' => array_map(fn (ReasonCategory $c): array => ['value' => $c->value, 'label' => $c->label()], ReasonCategory::cases()),
        ]);
    }

    public function store(Request $request, StartWorkflow $start): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.termination.initiate'), 403);

        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'effective_date' => ['required', 'date'],
            'termination_type' => ['required', 'string', 'in:'.implode(',', array_column(TerminationType::cases(), 'value'))],
            'reason_category' => ['required', 'string', 'in:'.implode(',', array_column(ReasonCategory::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rehireable' => ['boolean'],
        ]);

        $person = Person::findOrFail($validated['person_id']);

        $workflow = $start->handle(WorkflowType::Termination, $person, $request->user(), [
            'effective_date' => $validated['effective_date'],
            'termination_type' => $validated['termination_type'],
            'reason_category' => $validated['reason_category'],
            'notes' => $validated['notes'] ?? null,
            'rehireable' => $validated['rehireable'] ?? true,
        ]);

        $this->logWorkflow($workflow, 'started', "Termination initiated for {$person->name}");

        return redirect()->route('backoffice.terminations.show', $workflow->id)->with('success', 'Termination initiated.');
    }

    public function show(Request $request, Workflow $workflow): Response
    {
        abort_unless($request->user()->can('workflows.termination.initiate') || $request->user()->can('termination_records.view'), 403);
        $this->assertTermination($workflow);

        $person = Person::findOrFail($workflow->subject_id);
        $record = TerminationRecord::query()->where('workflow_id', $workflow->id)->firstOrFail();
        $current = $workflow->currentStep();

        $equipment = EquipmentAssignment::query()
            ->where('assigned_to_person_id', $person->id)
            ->where('status', EquipmentAssignmentStatus::Assigned)
            ->with('itemVariant.item:id,name')
            ->get()
            ->map(fn (EquipmentAssignment $a): array => [
                'id' => $a->id,
                'label' => $a->itemVariant->item->name.' — '.$a->itemVariant->label(),
                'quantity' => $a->quantity,
            ]);

        $user = $request->user();

        return Inertia::render('admin/terminations/show', [
            'workflow' => [
                'id' => $workflow->id,
                'status' => $workflow->status->label(),
                'is_terminal' => $workflow->status->isTerminal(),
                'current_step_key' => $current?->step_key,
            ],
            'person' => ['id' => $person->id, 'name' => $person->name, 'status' => $person->status->value],
            'record' => [
                'effective_date' => $record->effective_date->toDateString(),
                'type' => $record->termination_type->label(),
                'reason' => $record->reason_category->label(),
                'notes' => $record->notes,
                'rehireable' => $record->rehireable,
                'terminated_at' => $record->terminated_at?->toDateTimeString(),
                'final_paycheck_consolidated_cents' => $record->final_paycheck_consolidated_cents,
                'final_paycheck_remainder_cents' => $record->final_paycheck_remainder_cents,
                'final_paycheck_processed_at' => $record->final_paycheck_processed_at?->toDateTimeString(),
                'cancellation_reason' => $record->cancellation_reason,
            ],
            'steps' => $workflow->steps->map(fn (WorkflowStep $s): array => [
                'index' => $s->step_index,
                'key' => $s->step_key,
                'name' => $s->name,
                'actor' => $s->actor->value,
                'status' => $s->status->value,
                'completed_at' => $s->completed_at?->toDateTimeString(),
            ]),
            'context' => [
                'equipment' => $equipment,
                'outstanding_charge_cents' => $person->outstandingChargeBalance(),
                'open_period' => $this->openPeriodLabel($person),
            ],
            'can' => [
                'physical_tasks' => $user->can('workflows.termination.physical_tasks'),
                'payroll_tasks' => $user->can('workflows.termination.payroll_tasks'),
                'cancel' => $user->can('workflows.termination.cancel'),
            ],
        ]);
    }

    public function recoverEquipment(Request $request, Workflow $workflow, ReturnEquipment $returnEquipment, CompleteStep $complete): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.termination.physical_tasks'), 403);
        $this->assertTermination($workflow);
        $step = $this->currentStep($workflow, 'recover_equipment');
        $person = Person::findOrFail($workflow->subject_id);

        $validated = $request->validate([
            'items' => ['array'],
            'items.*.assignment_id' => ['required', 'integer', 'exists:equipment_assignments,id'],
            'items.*.returned' => ['required', 'boolean'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($validated['items'] ?? [] as $item) {
            $assignment = EquipmentAssignment::where('assigned_to_person_id', $person->id)->findOrFail($item['assignment_id']);
            $returnEquipment->handle($assignment, (bool) $item['returned'], $item['notes'] ?? null, $request->user());
        }

        $complete->handle($step, $request->user());
        $this->logWorkflow($workflow, 'step_completed', 'Equipment recovered');

        return back()->with('success', 'Equipment recovery recorded.');
    }

    public function moveFile(Request $request, Workflow $workflow, CompleteStep $complete): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.termination.physical_tasks'), 403);
        $this->assertTermination($workflow);
        $step = $this->currentStep($workflow, 'move_file');

        $complete->handle($step, $request->user());
        $this->logWorkflow($workflow, 'step_completed', 'File moved — person terminated');

        return back()->with('success', 'File marked moved. Person terminated.');
    }

    public function processFinalPaycheck(Request $request, Workflow $workflow, ProcessFinalPaycheck $process, CompleteStep $complete): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.termination.payroll_tasks'), 403);
        $this->assertTermination($workflow);
        $step = $this->currentStep($workflow, 'process_final_paycheck');
        $person = Person::findOrFail($workflow->subject_id);

        $result = $process->handle($person, $request->user());

        TerminationRecord::query()->where('workflow_id', $workflow->id)->firstOrFail()->update([
            'final_paycheck_period_id' => $result['period_id'],
            'final_paycheck_processed_at' => now(),
            'final_paycheck_processed_by' => $request->user()->id,
            'final_paycheck_consolidated_cents' => $result['consolidated_cents'],
            'final_paycheck_remainder_cents' => $result['remainder_cents'],
        ]);

        $complete->handle($step, $request->user());
        $this->logWorkflow($workflow, 'step_completed', 'Final paycheck processed');

        $message = $result['no_open_period']
            ? 'Final paycheck recorded — no open payroll period; outstanding charges flagged for manual handling.'
            : 'Final paycheck processed.';

        return back()->with('success', $message);
    }

    public function cancel(Request $request, Workflow $workflow, CancelWorkflow $cancel): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.termination.cancel'), 403);
        $this->assertTermination($workflow);

        if ($workflow->status->isTerminal()) {
            throw ValidationException::withMessages(['workflow' => 'This termination is already closed.']);
        }

        $moveFile = $workflow->steps()->where('step_key', 'move_file')->first();
        if ($moveFile !== null && $moveFile->status === StepStatus::Done) {
            throw ValidationException::withMessages(['workflow' => 'The file has already moved — this termination can no longer be cancelled.']);
        }

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $cancel->handle($workflow, $request->user(), $validated['reason']);
        $this->logWorkflow($workflow, 'cancelled', 'Termination cancelled');

        return redirect()->route('backoffice.terminations.index')->with('success', 'Termination cancelled.');
    }

    private function assertTermination(Workflow $workflow): void
    {
        abort_unless($workflow->type === WorkflowType::Termination->value, 404);
    }

    private function currentStep(Workflow $workflow, string $expectedKey): WorkflowStep
    {
        $step = $workflow->currentStep();

        if ($step === null || $step->step_key !== $expectedKey) {
            throw ValidationException::withMessages(['workflow' => 'This termination is not awaiting that action.']);
        }

        return $step;
    }

    private function openPeriodLabel(Person $person): ?string
    {
        $workOrder = $person->workOrders()->latest('id')->first();

        if ($workOrder === null) {
            return null;
        }

        return PayrollPeriod::query()
            ->where('property_id', $workOrder->property_id)
            ->where('status', PayrollPeriodStatus::Open)
            ->orderBy('week_start')
            ->first()?->week_start->toDateString();
    }
}
