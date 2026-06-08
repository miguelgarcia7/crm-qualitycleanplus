<?php

namespace App\Http\Controllers;

use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Actions\RejectStep;
use App\Domain\Workflows\Concerns\LogsWorkflowActivity;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared "My Tasks" surface (ADR-0026): lists the current pending workflow
 * steps assigned to the signed-in person (by person or by role) and lets them
 * complete or reject one. Generic over every workflow definition.
 */
class WorkflowTaskController extends Controller
{
    use LogsWorkflowActivity;

    public function index(Request $request): Response
    {
        $person = $request->user();

        $steps = WorkflowStep::query()
            ->openForPerson($person)
            ->with(['workflow.initiator:id,name'])
            ->latest('id')
            ->get();

        return Inertia::render('admin/tasks/index', [
            'tasks' => $steps->map(fn (WorkflowStep $step): array => [
                'id' => $step->id,
                'workflow_id' => $step->workflow_id,
                'workflow_type' => WorkflowType::from($step->workflow->type)->label(),
                'name' => $step->name,
                'step_type' => $step->step_type->label(),
                'initiator' => $step->workflow->initiator?->name,
                'created_at' => $step->created_at?->toDateTimeString(),
                'can_act' => $request->user()->can('act', $step),
            ])->values(),
        ]);
    }

    public function complete(Request $request, WorkflowStep $step, CompleteStep $action): RedirectResponse
    {
        $this->authorize('act', $step);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $workflow = $action->handle($step, $request->user(), $validated['notes'] ?? null);
        $this->logWorkflow($workflow, 'step_completed', "Completed step: {$step->name}");

        return back()->with('success', 'Task completed.');
    }

    public function reject(Request $request, WorkflowStep $step, RejectStep $action): RedirectResponse
    {
        $this->authorize('act', $step);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $workflow = $action->handle($step, $request->user(), $validated['reason']);
        $this->logWorkflow($workflow, 'step_rejected', "Rejected step: {$step->name}");

        return back()->with('success', 'Task rejected.');
    }
}
