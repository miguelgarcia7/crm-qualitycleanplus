<?php

namespace App\Http\Controllers\Minute;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * QC Minute self-service: an employee (contractor / W-2 / PM) requests a change
 * to their own name/email/phone (ADR — change_personal_info). HR verifies and
 * applies it in the back office.
 */
class ChangePersonalInfoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $mine = Workflow::query()
            ->where('type', WorkflowType::ChangePersonalInfo->value)
            ->where('initiator_id', $user->id)
            ->latest('id')
            ->get()
            ->map(fn (Workflow $wf): array => [
                'id' => $wf->id,
                'status' => $wf->status->value,
                'status_label' => $wf->status->label(),
                'changes' => $wf->data['changes'] ?? [],
                'created_at' => $wf->created_at?->toDateString(),
            ]);

        return Inertia::render('minute/info-changes/index', [
            'current' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
            'mine' => $mine,
            'can' => ['initiate' => $user->can('people.own_profile.request_change')],
        ]);
    }

    public function store(Request $request, StartWorkflow $start): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('people.own_profile.request_change'), 403);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:people,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $changes = [];
        foreach (['name', 'email', 'phone'] as $field) {
            $value = $validated[$field] ?? null;
            if ($value !== null && $value !== '' && $value !== $user->{$field}) {
                $changes[$field] = $value;
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['name' => 'Enter at least one new value that differs from your current info.']);
        }

        $start->handle(WorkflowType::ChangePersonalInfo, $user, $user, [
            'changes' => $changes,
            'reason' => $validated['reason'],
            'requested_by' => $user->id,
        ]);

        return back()->with('success', 'Change request submitted for verification.');
    }
}
