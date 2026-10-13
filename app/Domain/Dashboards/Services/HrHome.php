<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Dashboards\Support\DashboardFormat;
use App\Domain\Dashboards\Support\OnboardingQueue;
use App\Domain\Dashboards\Support\RequestQueue;
use App\Domain\Dashboards\Support\StaffAway;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoTier;
use App\Domain\Pto\Services\PtoTenure;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;

/**
 * The HR dashboard (docs/50-ui/dashboard-mockups/HR): hiring from application
 * to contractor, what HR verifies or approves, applicants missing documents
 * and ready to promote, terminations in progress, staff away, and staff whose
 * hire anniversary moves them up a time-off tier.
 */
class HrHome
{
    private const LIST_LIMIT = 5;

    private const STAFF_AWAY_DAYS = 30;

    /** How far ahead a time-off tier change is worth showing. */
    private const TIER_LOOKAHEAD_DAYS = 45;

    public function __construct(
        private readonly RequestQueue $requests,
        private readonly StaffAway $staffAway,
        private readonly PtoTenure $tenure,
        private readonly OnboardingQueue $onboarding,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Person $user): array
    {
        $now = CompanyTime::now();
        $today = $now->startOfDay();

        [$missing, $ready] = $this->onboarding->inReview($now);
        $strip = fn (array $rows): array => array_map(fn (array $row): array => collect($row)->except('missingKeys')->all(), $rows);
        [$missing, $ready] = [$strip($missing), $strip($ready)];

        return [
            'header' => [
                'greeting' => DashboardFormat::greeting($user, $now),
                'date' => $now->format('l, M j'),
                'contractors' => Person::query()->where('status', PersonStatus::ContractorActive->value)->count(),
                'staff' => Person::query()->where('status', PersonStatus::StaffActive->value)->count(),
            ],
            'actions' => [
                'invite' => $user->can('admin.users.create'),
                'newPosting' => $user->can('job_postings.manage'),
            ],
            'hiring' => [
                'new' => JobApplication::query()->where('status', JobApplicationStatus::Submitted->value)->count(),
                'inReview' => count($missing) + count($ready),
                'missingDocs' => count($missing),
                'ready' => count($ready),
                'placed' => JobApplication::query()->whereNotNull('promoted_at')->where('promoted_at', '>=', $today->startOfMonth()->utc())->count(),
                'openPostings' => JobPosting::query()->where('status', JobPostingStatus::Published->value)->count(),
            ],
            'waiting' => $this->requests->merge(array_values(array_filter([
                $user->can('workflows.change_personal_info.verify') ? $this->requests->infoChanges() : null,
                $user->can('workflows.pto.approve') ? $this->requests->timeOff() : null,
            ])), $now, self::LIST_LIMIT),
            'missing' => ['rows' => array_slice($missing, 0, self::LIST_LIMIT), 'total' => count($missing)],
            'ready' => [
                'rows' => array_slice($ready, 0, self::LIST_LIMIT),
                'total' => count($ready),
                'canPromote' => $user->can('people.applicants.promote'),
            ],
            'terminations' => $user->can('termination_records.view') || $user->can('workflows.termination.initiate')
                ? $this->terminations($now)
                : null,
            'staffAway' => $this->staffAway->upcoming($today, self::STAFF_AWAY_DAYS, self::LIST_LIMIT),
            'tiers' => $this->tiers($today),
        ];
    }

    /**
     * Terminations still running, with the step each is waiting on.
     *
     * @return array<string, mixed>
     */
    private function terminations(CarbonImmutable $now): array
    {
        $group = $this->requests->terminations();
        $rows = collect($group['rows'])->sortBy(fn (array $row) => $row['since']?->getTimestamp() ?? PHP_INT_MAX)->values();

        return [
            'rows' => $rows->take(self::LIST_LIMIT)->map(fn (array $row): array => [
                ...collect($row)->except('since')->all(),
                'age' => $row['since'] !== null ? DashboardFormat::age($row['since'], $now) : null,
            ])->all(),
            'total' => $group['count'],
        ];
    }

    /**
     * Active staff whose hire anniversary moves them up a time-off tier soon:
     * 30 days, 6 months and a year.
     *
     * @return list<array<string, mixed>>
     */
    private function tiers(CarbonImmutable $today): array
    {
        $milestones = [
            ['days' => 30, 'label' => '30 days'],
            ['months' => 6, 'label' => '6 months'],
            ['months' => 12, 'label' => '1 year'],
        ];

        return Person::query()
            ->where('status', PersonStatus::StaffActive->value)
            ->whereNotNull('hire_date')
            ->get(['id', 'name', 'hire_date'])
            ->map(function (Person $person) use ($milestones, $today): ?array {
                $hire = CarbonImmutable::parse($person->hire_date?->toDateString(), $today->timezone);

                foreach ($milestones as $milestone) {
                    $on = isset($milestone['days']) ? $hire->addDays($milestone['days']) : $hire->addMonthsNoOverflow($milestone['months']);
                    $days = (int) $today->diffInDays($on, false);

                    if ($days > 0 && $days <= self::TIER_LOOKAHEAD_DAYS) {
                        $tier = $this->tenure->tierAsOf($person, $on);

                        return [
                            'id' => $person->id,
                            'name' => $person->name,
                            'detail' => "{$milestone['label']} on ".$on->format('M j').' · '.$tier->hoursPerBucket().' h per bucket'.($tier === PtoTier::Tier3 ? ', the top tier' : ''),
                            'days' => $days,
                            'href' => "/admin/people/{$person->id}",
                        ];
                    }
                }

                return null;
            })
            ->filter()
            ->sortBy('days')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }
}
