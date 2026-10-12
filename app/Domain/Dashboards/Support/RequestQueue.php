<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The requests a role decides on, built per type and merged into one list,
 * oldest first ("Needs a decision", "Waiting on you"). Each builder takes the
 * property scope (null = every property) so the admin and a recruiter read the
 * same rules.
 *
 * A group is ['label', 'href', 'count', 'rows']; each row carries `since` (for
 * sorting and its age) and, when it stands for several requests, `represents`.
 *
 * @phpstan-type Group array{label: string, href: string, count: int, rows: list<array<string, mixed>>}
 */
class RequestQueue
{
    /**
     * @param  list<int>|null  $propertyIds
     * @return Group
     */
    public function staffing(?array $propertyIds): array
    {
        $requests = MoreStaffRequest::query()
            ->whereIn('status', [MoreStaffStatus::Submitted->value, MoreStaffStatus::InProgress->value])
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->with(['property:id,name', 'position:id,name', 'initiatedBy:id,name'])
            ->get();

        return $this->group('Staffing requests', '/admin/staffing-requests', $requests->map(fn (MoreStaffRequest $request): array => [
            'key' => "staffing-{$request->id}",
            'type' => 'Staffing',
            'title' => "{$request->property?->name} wants {$request->remaining()} more ".$request->position->name,
            'detail' => collect([
                "{$request->quantity_fulfilled} of {$request->quantity_requested} placed",
                $request->initiatedBy?->name !== null ? 'requested by '.$request->initiatedBy->name : null,
                'needed by '.$request->by_date->format('M j'),
            ])->filter()->implode(' · '),
            'since' => $request->created_at?->toImmutable(),
            'action' => ['label' => 'Place', 'href' => '/admin/staffing-requests'],
        ]));
    }

    /**
     * @param  list<int>|null  $propertyIds
     * @return Group
     */
    public function payIncreases(?array $propertyIds): array
    {
        $increases = $this->openWorkflows(WorkflowType::PayIncrease);
        $workOrders = WorkOrder::query()
            ->whereIn('id', $increases->map(fn (Workflow $w) => $w->data['work_order_id'] ?? null)->filter())
            ->with(['person:id,name', 'property:id,name', 'position:id,name'])
            ->get()
            ->keyBy('id');

        $rows = $increases
            ->filter(function (Workflow $workflow) use ($workOrders, $propertyIds): bool {
                $workOrder = $workOrders->get($workflow->data['work_order_id'] ?? 0);

                return $propertyIds === null || ($workOrder !== null && in_array($workOrder->property_id, $propertyIds, true));
            })
            ->map(function (Workflow $workflow) use ($workOrders): array {
                $workOrder = $workOrders->get($workflow->data['work_order_id'] ?? 0);
                $cents = (int) ($workflow->data['pm_requested_increase_cents'] ?? 0);

                return [
                    'key' => "pay-{$workflow->id}",
                    'type' => 'Pay increase',
                    'title' => $cents > 0
                        ? "{$workOrder?->property?->name} offers +$".number_format($cents / 100, 2)."/h for {$workOrder?->person?->name}"
                        : "Pay increase for {$workOrder?->person?->name}",
                    'detail' => collect([
                        $workOrder?->position?->name,
                        $cents === 0 ? $workOrder?->property?->name : null,
                        $workflow->initiator?->name !== null ? 'requested by '.$workflow->initiator->name : null,
                    ])->filter()->implode(' · '),
                    'since' => $workflow->created_at?->toImmutable(),
                    'action' => ['label' => 'Review', 'href' => '/admin/pay-increases'],
                ];
            });

        return $this->group('Pay increases', '/admin/pay-increases', $rows);
    }

    /**
     * @return Group
     */
    public function terminations(): array
    {
        $rows = $this->openWorkflows(WorkflowType::Termination, withSteps: true)->map(function (Workflow $workflow): array {
            $step = $workflow->steps->firstWhere('step_index', $workflow->current_step_index);

            return [
                'key' => "termination-{$workflow->id}",
                'type' => 'Termination',
                'title' => $workflow->subject instanceof Person ? $workflow->subject->name : 'Termination',
                'detail' => $step !== null
                    ? 'Next: '.lcfirst($step->name).($step->assigned_role !== null ? ' · '.str_replace('_', ' ', $step->assigned_role) : '')
                    : 'In progress',
                'since' => $workflow->created_at?->toImmutable(),
                'action' => ['label' => 'Open', 'href' => "/admin/terminations/{$workflow->id}"],
            ];
        });

        return $this->group('Terminations', '/admin/terminations', $rows);
    }

    /**
     * @return Group
     */
    public function timeOff(): array
    {
        $rows = PtoRequest::query()->pending()->with(['person:id,name', 'allotment'])->get()->map(fn (PtoRequest $request): array => [
            'key' => "pto-{$request->id}",
            'type' => 'Time off',
            'title' => "{$request->person?->name} · ".DashboardFormat::range($request->start_date, $request->end_date),
            // Hours are set aside when the request is made, so what's available
            // now is what's left if it's approved.
            'detail' => self::hours((float) $request->hours).' h '.strtolower($request->bucket->label())
                .($request->allotment !== null ? ' · '.self::hours($request->allotment->availableFor($request->bucket)).' h left after this' : ''),
            'since' => ($request->submitted_at ?? $request->created_at)?->toImmutable(),
            'action' => ['label' => 'Review', 'href' => '/admin/pto'],
        ]);

        return $this->group('Time off', '/admin/pto', $rows);
    }

    /**
     * @return Group
     */
    public function infoChanges(): array
    {
        $rows = $this->openWorkflows(WorkflowType::ChangePersonalInfo)->map(fn (Workflow $workflow): array => [
            'key' => "info-{$workflow->id}",
            'type' => 'Info change',
            'title' => ($workflow->subject instanceof Person ? $workflow->subject->name : 'Someone').' · '.self::changed($workflow),
            'detail' => 'Verify before it replaces what is on file',
            'since' => $workflow->created_at?->toImmutable(),
            'action' => ['label' => 'Verify', 'href' => '/admin/info-changes'],
        ]);

        return $this->group('Info changes', '/admin/info-changes', $rows);
    }

    /**
     * Applications waiting on review, as one row: they are worked as a queue.
     * Scoped to postings at the given properties.
     *
     * @param  list<int>|null  $propertyIds
     * @return Group
     */
    public function applications(?array $propertyIds): array
    {
        $applications = JobApplication::query()
            ->pending()
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereHas('jobPosting', fn (Builder $p) => $p->whereIn('property_id', $propertyIds)));
        $count = $applications->clone()->count();

        $rows = collect();
        if ($count > 0) {
            $oldest = CarbonImmutable::parse((string) $applications->clone()->min('submitted_at'));

            $rows->push([
                'key' => 'applications',
                'type' => 'Applications',
                'title' => DashboardFormat::plural($count, 'application', 'applications').' to review',
                'detail' => $propertyIds === null ? 'Oldest sent '.$oldest->format('M j') : 'For postings at your properties',
                'since' => $oldest,
                'represents' => $count,
                'action' => ['label' => 'Review', 'href' => '/admin/applicants'],
            ]);
        }

        return ['label' => 'Applications', 'href' => '/admin/applicants', 'count' => $count, 'rows' => $rows->all()];
    }

    /**
     * Frozen invoices nobody has sent yet.
     *
     * @param  list<int>|null  $propertyIds
     * @return Group
     */
    public function invoicesToSend(?array $propertyIds): array
    {
        $rows = Invoice::query()
            ->awaitingSend()
            ->whereNull('notification_sent_at')
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->with(['property:id,name', 'payrollPeriod:id,week_start,week_end'])
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'key' => "invoice-{$invoice->id}",
                'type' => 'Invoice',
                'title' => "Send {$invoice->property?->name}'s invoice".($invoice->payrollPeriod !== null
                    ? ' for '.DashboardFormat::range($invoice->payrollPeriod->week_start, $invoice->payrollPeriod->week_end)
                    : ''),
                'detail' => 'Issued '.$invoice->issue_date->format('M j').' · $'.number_format($invoice->total / 100, 2),
                'since' => $invoice->issue_date,
                'action' => ['label' => 'Send', 'href' => "/admin/invoices/{$invoice->id}"],
            ]);

        return $this->group('Invoices to send', '/admin/invoices?status=invoiced', $rows);
    }

    /**
     * Applicants in review who are still missing onboarding items, for
     * postings at the given properties.
     *
     * @param  list<int>|null  $propertyIds
     * @return Group
     */
    public function onboarding(?array $propertyIds): array
    {
        $applications = JobApplication::query()
            ->where('status', JobApplicationStatus::Reviewing->value)
            ->whereHas('person', fn (Builder $q) => $q->where('status', PersonStatus::Applicant->value))
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereHas('jobPosting', fn (Builder $p) => $p->whereIn('property_id', $propertyIds)))
            ->with('person')
            ->get();

        $labels = collect(OnboardingChecklist::DOCUMENTS)->map(fn (array $doc): string => $doc[0])->put('background_check', 'Background check');

        $rows = $applications
            ->map(function (JobApplication $application) use ($labels): ?array {
                $missing = OnboardingChecklist::missingItems($application->person);
                if ($missing === []) {
                    return null;
                }

                return [
                    'key' => "onboarding-{$application->id}",
                    'type' => 'Onboarding',
                    'title' => "{$application->person->name} is missing ".DashboardFormat::plural(count($missing), 'item', 'items'),
                    'detail' => collect($missing)->map(fn (string $key): string => (string) ($labels[$key] ?? $key))->implode(', '),
                    'since' => ($application->reviewed_at ?? $application->submitted_at)->toImmutable(),
                    'action' => ['label' => 'Open', 'href' => "/admin/applicants/{$application->id}"],
                ];
            })
            ->filter();

        return $this->group('Onboarding', '/admin/applicants?status=reviewing', $rows);
    }

    /**
     * Merge the groups into one list, oldest first, capped at $limit.
     *
     * @param  list<Group>  $groups
     * @return array{rows: list<array<string, mixed>>, total: int, hidden: int, byType: list<array{label: string, href: string, count: int}>}
     */
    public function merge(array $groups, CarbonImmutable $now, int $limit): array
    {
        $sorted = collect($groups)
            ->flatMap(fn (array $group): array => $group['rows'])
            ->sortBy(fn (array $row) => $row['since']?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
        $shown = $sorted->take($limit);
        $total = (int) collect($groups)->sum('count');

        return [
            'rows' => $shown->map(fn (array $row): array => [
                ...collect($row)->except(['since', 'represents'])->all(),
                'age' => $row['since'] !== null ? DashboardFormat::age($row['since'], $now) : null,
            ])->all(),
            'total' => $total,
            // Requests not on screen; a grouped row stands for all it represents.
            'hidden' => $total - (int) $shown->sum(fn (array $row): int => $row['represents'] ?? 1),
            'byType' => collect($groups)
                ->where('count', '>', 0)
                ->map(fn (array $group): array => ['label' => $group['label'], 'count' => $group['count'], 'href' => $group['href']])
                ->values()
                ->all(),
        ];
    }

    /** "new phone number", "new email and phone number". */
    private static function changed(Workflow $workflow): string
    {
        $words = ['name' => 'name', 'email' => 'email', 'phone' => 'phone number'];
        $fields = collect(array_keys((array) ($workflow->data['changes'] ?? [])))
            ->map(fn (string $field): string => $words[$field] ?? str_replace('_', ' ', $field));

        return $fields->isEmpty() ? 'updated their details' : 'new '.$fields->join(', ', ' and ');
    }

    /** 40, 7.5 — no trailing zeros. */
    private static function hours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  iterable<array<string, mixed>|null>  $rows
     * @return Group
     */
    private function group(string $label, string $href, iterable $rows): array
    {
        $rows = collect($rows)->filter()->values();

        return ['label' => $label, 'href' => $href, 'count' => $rows->count(), 'rows' => $rows->all()];
    }

    /**
     * @return Collection<int, Workflow>
     */
    private function openWorkflows(WorkflowType $type, bool $withSteps = false): Collection
    {
        return Workflow::query()
            ->where('type', $type->value)
            ->where('status', WorkflowStatus::InProgress->value)
            ->with(array_filter(['initiator:id,name', 'subject', $withSteps ? 'steps' : null]))
            ->get();
    }
}
