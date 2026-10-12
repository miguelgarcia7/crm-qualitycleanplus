<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Carbon\CarbonImmutable;

/**
 * Applicants in review and where their onboarding stands: who is still missing
 * documents (HR chases, the front desk collects) and who is ready to promote.
 */
class OnboardingQueue
{
    /** Short names for the onboarding items, sized for a chip. */
    private const SHORT = [
        'id_front' => 'ID (front)',
        'id_back' => 'ID (back)',
        'i9' => 'I-9',
        'w9' => 'W-9',
        'contractor_agreement' => 'Signed agreement',
        'background_check' => 'Background check',
    ];

    /**
     * Applicants in review, split into those still missing onboarding items
     * (longest waiting first) and those with everything in.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    public function inReview(CarbonImmutable $now): array
    {
        $applications = JobApplication::query()
            ->where('status', JobApplicationStatus::Reviewing->value)
            ->whereHas('person', fn ($q) => $q->where('status', PersonStatus::Applicant->value))
            ->with(['person', 'jobPosting.property:id,name'])
            ->orderBy('reviewed_at')
            ->orderBy('submitted_at')
            ->get();

        $rows = $applications->map(function (JobApplication $application) use ($now): array {
            $missing = OnboardingChecklist::missingItems($application->person);
            $since = ($application->reviewed_at ?? $application->submitted_at)->toImmutable();

            return [
                'id' => $application->id,
                'name' => $application->person->name,
                'missing' => array_map(fn (string $key): string => self::SHORT[$key] ?? $key, $missing),
                'missingKeys' => $missing,
                'role' => $application->job_posting_id !== null
                    ? collect([$application->jobPosting->title, $application->jobPosting->locationName()])->filter()->implode(' · ').' posting'
                    : (string) $application->desired_position,
                'age' => DashboardFormat::age($since, $now),
                'href' => "/admin/applicants/{$application->id}",
            ];
        });

        return [
            $rows->filter(fn (array $row): bool => $row['missing'] !== [])->values()->all(),
            $rows->filter(fn (array $row): bool => $row['missing'] === [])->values()->all(),
        ];
    }
}
