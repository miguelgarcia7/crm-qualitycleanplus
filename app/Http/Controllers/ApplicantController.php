<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Applicant review queue (Phase 08b-ii): applications come in from the public
 * form (08b-i), staff start review or reject here, complete the onboarding
 * checklist on the detail page, and promote to contractor (Inc 3 actions).
 * Authorization via JobApplicationPolicy (ADR-0013 role split).
 */
class ApplicantController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JobApplication::class);

        $filter = (string) $request->query('status', 'pending');

        $applications = JobApplication::query()
            ->with(['person:id,name,email,phone,city,state,status', 'jobPosting:id,title,slug'])
            ->when($filter === 'pending', fn ($q) => $q->pending())
            ->when(
                in_array($filter, ['submitted', 'reviewing', 'promoted', 'rejected'], true),
                fn ($q) => $q->where('status', $filter),
            )
            ->latest('submitted_at')
            ->get()
            ->map(fn (JobApplication $a): array => [
                'id' => $a->id,
                'name' => $a->person->name,
                'city' => trim(implode(', ', array_filter([$a->person->city, $a->person->state]))),
                'desired_position' => $a->desired_position,
                'posting' => $a->jobPosting?->title,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'submitted_at' => $a->submitted_at->toDayDateTimeString(),
            ]);

        // toBase(): keep raw status strings — the enum cast would make unusable keys.
        $counts = JobApplication::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('admin/applicants/index', [
            'applications' => $applications,
            'filter' => $filter,
            'counts' => [
                'pending' => ($counts['submitted'] ?? 0) + ($counts['reviewing'] ?? 0),
                'submitted' => $counts['submitted'] ?? 0,
                'reviewing' => $counts['reviewing'] ?? 0,
                'promoted' => $counts['promoted'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
            ],
        ]);
    }

    public function show(Request $request, JobApplication $application): Response
    {
        $this->authorize('view', $application);

        $application->load(['person', 'jobPosting:id,title,slug', 'reviewedBy:id,name']);
        /** @var Person $person */
        $person = $application->person;
        /** @var Person $user */
        $user = $request->user();

        return Inertia::render('admin/applicants/show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'submitted_at' => $application->submitted_at->toDayDateTimeString(),
                'posting' => $application->jobPosting?->title,
                'desired_position' => $application->desired_position,
                'desired_salary' => $application->desired_salary,
                'desired_start_date' => $application->desired_start_date?->toFormattedDateString(),
                'transportation' => $application->transportation,
                'work_at_qcp' => $application->work_at_qcp,
                'work_at_qcp_explain' => $application->work_at_qcp_explain,
                'another_staff_agency' => $application->another_staff_agency,
                'non_complete' => $application->non_complete,
                'convicted_felon' => $application->convicted_felon,
                'felony_conviction' => $application->felony_conviction,
                'acknowledgement' => $application->acknowledgement,
                'reviewed_by' => $application->reviewedBy?->name,
                'reviewed_at' => $application->reviewed_at?->toDayDateTimeString(),
                'rejected_reason' => $application->rejected_reason,
                'promoted_at' => $application->promoted_at?->toDayDateTimeString(),
            ],
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'status' => $person->status->value,
                'email' => str_ends_with((string) $person->email, '@qcp.invalid') ? null : $person->email,
                'phone' => $person->phone,
                'dob' => $person->dob?->toFormattedDateString(),
                'address' => trim(implode(', ', array_filter([$person->address, $person->apartment_number, $person->city, $person->state, $person->zip]))),
                'usa_citizen' => $person->usa_citizen,
                'eligible_to_work' => $person->eligible_to_work,
                'emergency_contact' => trim(implode(' · ', array_filter([
                    $person->emergency_contact_name,
                    $person->emergency_contact_phone,
                    $person->emergency_contact_relationship,
                ]))),
                'application_date' => $person->application_date?->toFormattedDateString(),
                'has_work_orders' => $person->workOrders()->exists(),
            ],
            'other_applications' => $person->jobApplications()
                ->whereKeyNot($application->id)
                ->latest('submitted_at')
                ->get()
                ->map(fn (JobApplication $a): array => [
                    'id' => $a->id,
                    'status_label' => $a->status->label(),
                    'desired_position' => $a->desired_position,
                    'submitted_at' => $a->submitted_at->toFormattedDateString(),
                ]),
            'checklist' => OnboardingChecklist::status($person),
            'checklist_complete' => OnboardingChecklist::isComplete($person),
            'can' => [
                'review' => $user->can('review', $application),
                'edit_checklist' => $user->can('editChecklist', $application),
                'waive' => $user->hasAnyRole(['hr', 'admin', 'super_admin']),
                'promote' => $user->can('promote', $application),
                'reverse' => $user->can('reverse', $application),
            ],
        ]);
    }

    public function startReview(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('review', $application);

        abort_unless($application->status === JobApplicationStatus::Submitted, 422, 'Only submitted applications can move to reviewing.');

        $application->update([
            'status' => JobApplicationStatus::Reviewing,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Application moved to reviewing.');
    }

    public function reject(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('review', $application);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        abort_unless($application->status->isPending(), 422, 'Only pending applications can be rejected.');

        $application->update([
            'status' => JobApplicationStatus::Rejected,
            'rejected_reason' => $validated['reason'],
            'reviewed_by' => $application->reviewed_by ?? $request->user()?->id,
            'reviewed_at' => $application->reviewed_at ?? now(),
        ]);

        return back()->with('success', 'Application rejected.');
    }
}
