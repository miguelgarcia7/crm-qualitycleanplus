<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Actions\RejectStep;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Concerns\LogsWorkflowActivity;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office change-personal-info (20-domain/workflows.md): HR verifies pending
 * requests (current vs proposed name/email/phone) and applies or declines; HR /
 * recruiters / admins may also initiate a change on behalf of someone. The apply
 * happens in the definition's verify-step hook.
 */
class ChangePersonalInfoController extends Controller
{
    use LogsWorkflowActivity;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canVerify = $user->can('workflows.change_personal_info.verify');

        $queue = $canVerify
            ? Workflow::query()
                ->where('type', WorkflowType::ChangePersonalInfo->value)
                ->where('status', WorkflowStatus::InProgress->value)
                ->with('initiator:id,name')
                ->latest('id')
                ->get()
                ->map(fn (Workflow $wf): ?array => $this->queuePayload($wf))
                ->filter()
                ->values()
            : collect();

        $mine = Workflow::query()
            ->where('type', WorkflowType::ChangePersonalInfo->value)
            ->where('initiator_id', $user->id)
            ->latest('id')
            ->get()
            ->map(fn (Workflow $wf): array => [
                'id' => $wf->id,
                'status' => $wf->status->label(),
                'changes' => $wf->data['changes'] ?? [],
                'created_at' => $wf->created_at?->toDateString(),
            ]);

        return Inertia::render('admin/info-changes/index', [
            'queue' => $queue,
            'mine' => $mine,
            'people' => Person::query()->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'can' => [
                'verify' => $canVerify,
                'initiate' => $user->can('people.own_profile.request_change') || $canVerify,
            ],
        ]);
    }

    public function store(Request $request, StartWorkflow $start): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can('people.own_profile.request_change') || $user->can('workflows.change_personal_info.verify'),
            403,
        );

        $subject = Person::findOrFail($request->integer('person_id'));
        $changes = $this->validateChanges($request, $subject);

        $workflow = $start->handle(WorkflowType::ChangePersonalInfo, $subject, $user, [
            'changes' => $changes,
            'reason' => $request->string('reason')->toString(),
            'requested_by' => $user->id,
        ]);
        $this->logWorkflow($workflow, 'started', "Personal-info change requested for {$subject->name}");

        return back()->with('success', 'Change request submitted for verification.');
    }

    public function approve(Request $request, Workflow $workflow, CompleteStep $action): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.change_personal_info.verify'), 403);

        $action->handle($this->verifyStep($workflow), $request->user());

        return back()->with('success', 'Change verified and applied.');
    }

    public function decline(Request $request, Workflow $workflow, RejectStep $action): RedirectResponse
    {
        abort_unless($request->user()->can('workflows.change_personal_info.verify'), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->handle($this->verifyStep($workflow), $request->user(), $validated['reason']);

        return back()->with('success', 'Change request declined.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function queuePayload(Workflow $workflow): ?array
    {
        $person = Person::find($workflow->subject_id);
        $step = $workflow->steps()->where('step_key', 'verify_change')->where('status', 'pending')->first();

        if ($person === null || $step === null) {
            return null;
        }

        return [
            'id' => $workflow->id,
            'person' => $person->name,
            'requested_by' => $workflow->initiator?->name,
            'reason' => $workflow->data['reason'] ?? null,
            'current' => ['name' => $person->name, 'email' => $person->email, 'phone' => $person->phone],
            'changes' => $workflow->data['changes'] ?? [],
        ];
    }

    /**
     * Validate the submitted fields and return only those that actually differ.
     *
     * @return array<string, string>
     */
    private function validateChanges(Request $request, Person $subject): array
    {
        $validated = $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:people,email,'.$subject->id],
            'phone' => ['nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $changes = [];
        foreach (['name', 'email', 'phone'] as $field) {
            $value = $validated[$field] ?? null;
            if ($value !== null && $value !== '' && $value !== $subject->{$field}) {
                $changes[$field] = $value;
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['name' => 'Enter at least one new value that differs from the current info.']);
        }

        return $changes;
    }

    private function verifyStep(Workflow $workflow): WorkflowStep
    {
        abort_unless($workflow->type === WorkflowType::ChangePersonalInfo->value, 404);
        $step = $workflow->steps()->where('step_key', 'verify_change')->where('status', 'pending')->first();

        if ($step === null) {
            throw ValidationException::withMessages(['workflow' => 'This request is not awaiting verification.']);
        }

        return $step;
    }
}
