<?php

namespace App\Http\Controllers;

use App\Domain\People\Actions\PromoteApplicantToContractor;
use App\Domain\People\Actions\ReversePromotion;
use App\Domain\People\Actions\UploadOnboardingDocument;
use App\Domain\People\Enums\BackgroundCheckStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Shared\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // The full set ships to the client: status filtering (bordered tabs),
        // search, sorting, and pagination all happen in the DataTable, matching
        // the theme's HRM table pattern. `?status=` seeds the initial tab so
        // dashboard deep-links still land on the right slice.
        $applications = JobApplication::query()
            ->with(['person:id,name,email,phone,city,state,status', 'jobPosting:id,title,slug'])
            ->latest('submitted_at')
            ->get()
            ->map(fn (JobApplication $a): array => [
                'id' => $a->id,
                'name' => $a->person->name,
                'email' => str_ends_with((string) $a->person->email, '@qcp.invalid') ? null : $a->person->email,
                'phone' => $a->person->phone,
                'city' => trim(implode(', ', array_filter([$a->person->city, $a->person->state]))),
                'desired_position' => $a->desired_position,
                'posting' => $a->jobPosting?->title,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'submitted_at' => $a->submitted_at->toIso8601String(),
                'submitted_at_display' => $a->submitted_at->toDayDateTimeString(),
            ]);

        return Inertia::render('admin/applicants/index', [
            'applications' => $applications,
            'filter' => (string) $request->query('status', 'pending'),
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

    public function uploadDocument(Request $request, JobApplication $application, string $item, UploadOnboardingDocument $action): RedirectResponse
    {
        $this->authorize('editChecklist', $application);

        abort_unless(array_key_exists($item, OnboardingChecklist::DOCUMENTS), 404);

        $validated = $request->validate([
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ]);

        /** @var Person $person */
        $person = $application->person;
        /** @var Person $actor */
        $actor = $request->user();

        $action->handle($person, $item, $validated['document'], $actor);

        return back()->with('success', 'Document uploaded.');
    }

    public function downloadDocument(JobApplication $application, string $item): StreamedResponse
    {
        $this->authorize('editChecklist', $application);

        $definition = OnboardingChecklist::DOCUMENTS[$item] ?? abort(404);
        /** @var Person $person */
        $person = $application->person;

        $fileId = $person->getAttribute($definition[1]);
        abort_if($fileId === null, 404);

        /** @var File $file */
        $file = File::query()->findOrFail($fileId);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function verifyI9(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('editChecklist', $application);

        /** @var Person $person */
        $person = $application->person;

        abort_if($person->i9_file_id === null, 422, 'Upload the I-9 before verifying it.');

        $person->forceFill(['i9_verified_by' => $request->user()?->id, 'i9_verified_at' => now()])->save();

        return back()->with('success', 'I-9 verified.');
    }

    public function setBackgroundCheck(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('editChecklist', $application);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(BackgroundCheckStatus::class)],
        ]);

        $status = BackgroundCheckStatus::from($validated['status']);
        /** @var Person $person */
        $person = $application->person;

        $person->forceFill([
            'background_check_status' => $status,
            'background_check_completed_at' => in_array($status, [BackgroundCheckStatus::Passed, BackgroundCheckStatus::Failed], true) ? now() : null,
        ])->save();

        return back()->with('success', "Background check: {$status->label()}.");
    }

    /** Waive/unwaive a checklist item — an HR power (people-lifecycle.md). */
    public function waive(Request $request, JobApplication $application, string $item): RedirectResponse
    {
        $this->authorize('editChecklist', $application);
        abort_unless($request->user()?->hasAnyRole(['hr', 'admin', 'super_admin']), 403);
        abort_unless(in_array($item, OnboardingChecklist::ITEMS, true), 404);

        $validated = $request->validate(['waived' => ['required', 'boolean']]);

        /** @var Person $person */
        $person = $application->person;
        $waived = OnboardingChecklist::waivedItems($person);

        $waived = $validated['waived']
            ? array_values(array_unique([...$waived, $item]))
            : array_values(array_diff($waived, [$item]));

        $person->forceFill(['onboarding_waived_items' => $waived])->save();

        return back()->with('success', $validated['waived'] ? 'Item waived.' : 'Waiver removed.');
    }

    public function promote(Request $request, JobApplication $application, PromoteApplicantToContractor $action): RedirectResponse
    {
        $this->authorize('promote', $application);

        /** @var Person $promoter */
        $promoter = $request->user();
        $action->handle($application, $promoter);

        return back()->with('success', "{$application->person->name} is now an active contractor.");
    }

    public function reverse(Request $request, JobApplication $application, ReversePromotion $action): RedirectResponse
    {
        $this->authorize('reverse', $application);

        /** @var Person $actor */
        $actor = $request->user();
        $action->handle($application, $actor);

        return back()->with('success', 'Promotion reversed — back to applicant.');
    }
}
