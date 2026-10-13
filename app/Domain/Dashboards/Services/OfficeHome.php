<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Dashboards\Support\ApprovalQueue;
use App\Domain\Dashboards\Support\DashboardFormat;
use App\Domain\Dashboards\Support\LowStock;
use App\Domain\Dashboards\Support\SystemAlerts;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;

/**
 * The office manager's dashboard (docs/50-ui/dashboard-mockups/OfficeManager):
 * hours on their way to invoices with the ready ones to send, punches left
 * open, import-only properties' files, supply requests, low stock and website
 * inquiries.
 */
class OfficeHome
{
    private const LIST_LIMIT = 5;

    /** An open punch older than this is a missed clock-out, not a long shift. */
    private const OPEN_PUNCH_HOURS = 12;

    public function __construct(
        private readonly ApprovalQueue $approvals,
        private readonly SystemAlerts $alerts,
        private readonly LowStock $lowStock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Person $user): array
    {
        $now = CompanyTime::now();
        $today = $now->startOfDay();

        return [
            'header' => [
                'greeting' => DashboardFormat::greeting($user, $now),
                'date' => $now->format('l, M j'),
                'properties' => Property::query()->count(),
            ],
            'actions' => [
                'newWorkOrder' => $user->can('work_orders.create'),
                'upload' => $user->can('imports.upload'),
            ],
            'alerts' => $this->alerts->for($user),
            'pipeline' => $this->pipeline($user, $now),
            'punches' => $this->punches($now),
            'imports' => $user->can('imports.upload') ? $this->imports($today) : null,
            'supplies' => $user->can('workflows.supply_request.fulfill') ? $this->supplies($now) : null,
            'stock' => $user->can('inventory.items.view') ? $this->lowStock->build(self::LIST_LIMIT) : null,
            'inquiries' => $user->can('marketing.inquiries.manage') ? $this->inquiries($now) : null,
        ];
    }

    /**
     * Not submitted, with the property manager, ready to send, sent this
     * week; and the invoices ready to send, oldest first.
     *
     * @return array<string, mixed>
     */
    private function pipeline(Person $user, CarbonImmutable $now): array
    {
        $unsent = $this->approvals->unsentByProperty($now->startOfDay());
        $pending = $this->approvals->query(null);
        $overdue = $pending->clone()->where('sent_for_approval_at', '<', $now->utc()->subDays(ApprovalQueue::SLA_DAYS))->count();

        $ready = Invoice::query()->awaitingSend()->whereNull('notification_sent_at');
        $readyCount = $ready->clone()->count();

        return [
            'notSubmitted' => count($unsent),
            'notSubmittedWeeks' => array_sum($unsent),
            'awaiting' => $pending->clone()->count(),
            'awaitingOverdue' => $overdue,
            'ready' => $readyCount,
            'readyAmount' => round(((int) $ready->clone()->sum('total')) / 100, 2),
            'sent' => Invoice::query()
                ->where('status', '!=', InvoiceStatus::Voided->value)
                ->where('notification_sent_at', '>=', $now->startOfWeek(CarbonImmutable::MONDAY)->utc())
                ->count(),
            'canSend' => $user->can('invoices.send'),
            'invoices' => $ready->clone()
                ->with('property:id,name,billing_email')
                ->orderBy('issue_date')
                ->orderBy('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'propertyId' => $invoice->property_id,
                    'property' => $invoice->property?->name,
                    // The property's current billing address; the snapshot is
                    // what it was when the invoice was generated.
                    'email' => $invoice->property?->billing_email ?: ($invoice->property_snapshot['billing_email'] ?? null),
                    'total' => round($invoice->total / 100, 2),
                    'href' => "/admin/invoices/{$invoice->id}",
                ])
                ->all(),
        ];
    }

    /**
     * Punches open for more than twelve hours, oldest first, with who looks
     * after the property.
     *
     * @return array<string, mixed>
     */
    private function punches(CarbonImmutable $now): array
    {
        $open = TimeEntry::query()
            ->whereNotNull('start_at_utc')
            ->whereNull('end_at_utc')
            ->where('start_at_utc', '<', $now->subHours(self::OPEN_PUNCH_HOURS)->utc());

        $entries = $open->clone()
            ->with(['person:id,name', 'property:id,name,timezone', 'payrollPeriod:id,week_start'])
            ->orderBy('start_at_utc')
            ->limit(self::LIST_LIMIT)
            ->get();

        $recruiters = PropertyAssignment::query()
            ->where('role', PropertyAssignmentRole::Recruiter->value)
            ->whereIn('property_id', $entries->pluck('property_id'))
            ->with('person:id,name')
            ->get()
            ->groupBy('property_id');

        return [
            'rows' => $entries->map(fn (TimeEntry $entry): array => [
                'id' => $entry->id,
                'contractor' => $entry->person->name,
                'personId' => $entry->person_id,
                'property' => $entry->property->name,
                'clockedIn' => $entry->start_at_utc?->setTimezone($entry->timezone ?? $entry->property->timezone)->format('D, M j · g:i A'),
                'recruiter' => $recruiters->get($entry->property_id)?->map(fn (PropertyAssignment $a): ?string => $a->person?->name)->filter()->implode(', ') ?: null,
                'href' => "/admin/properties/{$entry->property_id}/grid".($entry->payrollPeriod !== null ? '?week='.$entry->payrollPeriod->week_start->toDateString() : ''),
            ])->all(),
            'total' => $open->clone()->count(),
            'hours' => self::OPEN_PUNCH_HOURS,
        ];
    }

    /**
     * Import-only properties and whether last week's spreadsheet is in:
     * missing files first.
     *
     * @return array<string, mixed>
     */
    private function imports(CarbonImmutable $today): array
    {
        $properties = Property::query()->where('time_source', PropertyTimeSource::Import->value)->orderBy('name')->get(['id', 'name']);

        $periods = PayrollPeriod::query()
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereDate('week_end', '>=', $today->subDays(7)->toDateString())
            ->whereDate('week_end', '<', $today->toDateString())
            ->get(['id', 'property_id', 'week_start', 'week_end'])
            ->keyBy('property_id');

        $batches = ImportBatch::query()
            ->whereIn('payroll_period_id', $periods->pluck('id'))
            ->where('status', '!=', ImportBatchStatus::RolledBack->value)
            ->latest('id')
            ->get(['id', 'payroll_period_id', 'status', 'applied_at', 'created_at'])
            ->unique('payroll_period_id')
            ->keyBy('payroll_period_id');

        $rows = $properties->map(function (Property $property) use ($periods, $batches): array {
            $period = $periods->get($property->id);
            $batch = $period !== null ? $batches->get($period->id) : null;
            $week = $period !== null ? DashboardFormat::range($period->week_start, $period->week_end) : 'last week';

            return [
                'id' => $property->id,
                'name' => $property->name,
                'in' => $batch !== null && $batch->status === ImportBatchStatus::Applied,
                'detail' => match (true) {
                    $batch === null => "No file for {$week} yet",
                    $batch->status === ImportBatchStatus::Applied => 'Imported '.$batch->applied_at?->setTimezone(CompanyTime::zone())->format('D, M j'),
                    default => "Uploaded, not committed yet ({$week})",
                },
                'action' => $batch === null
                    ? ['label' => 'Upload', 'href' => "/admin/imports/create?property_id={$property->id}"]
                    : ['label' => $batch->status === ImportBatchStatus::Applied ? 'View' : 'Finish', 'href' => "/admin/imports/{$batch->id}"],
            ];
        })->sortBy(fn (array $row): int => $row['in'] ? 1 : 0)->values();

        return [
            'rows' => $rows->take(self::LIST_LIMIT)->all(),
            'total' => $rows->count(),
            'in' => $rows->where('in', true)->count(),
        ];
    }

    /**
     * Supply requests waiting to be filled, oldest first.
     *
     * @return array<string, mixed>
     */
    private function supplies(CarbonImmutable $now): array
    {
        $requests = SupplyRequest::query()->pending();

        return [
            'rows' => $requests->clone()
                ->with(['category:id,name', 'itemVariant.item:id,name', 'requestedBy:id,name', 'beneficiary:id,name'])
                ->oldest('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (SupplyRequest $request): array => [
                    'id' => $request->id,
                    'title' => collect([
                        $request->category?->name,
                        $request->itemVariant !== null
                            ? $request->itemVariant->item->name.($request->itemVariant->label() !== 'Default' ? ' ('.$request->itemVariant->label().')' : '')
                            : $request->proposed_item_name,
                    ])->filter()->implode(' · ').($request->quantity > 1 ? " × {$request->quantity}" : ''),
                    'detail' => collect([
                        $request->requestedBy?->name !== null ? 'requested by '.$request->requestedBy->name : null,
                        $request->beneficiary?->name !== null && $request->beneficiary_person_id !== $request->requested_by ? 'for '.$request->beneficiary->name : null,
                    ])->filter()->implode(' · '),
                    'age' => $request->created_at !== null ? DashboardFormat::age($request->created_at->toImmutable(), $now) : null,
                ])
                ->all(),
            'total' => $requests->clone()->count(),
        ];
    }

    /**
     * New website inquiries: each business lead, job seekers as one row.
     *
     * @return array<string, mixed>
     */
    private function inquiries(CarbonImmutable $now): array
    {
        $new = ContactInquiry::query()->whereNull('handled_at');

        $business = $new->clone()->where('type', 'business')->latest('id')->limit(self::LIST_LIMIT)->get()
            ->map(fn (ContactInquiry $inquiry): array => [
                'key' => "business-{$inquiry->id}",
                'type' => 'business',
                'title' => $inquiry->company ?: $inquiry->name(),
                'detail' => (string) str((string) ($inquiry->inquiry_type ?: $inquiry->message))->limit(60),
                'age' => $inquiry->created_at !== null ? DashboardFormat::age($inquiry->created_at->toImmutable(), $now) : null,
            ]);

        $seekers = $new->clone()->where('type', 'job_seeker');
        $seekerCount = $seekers->clone()->count();

        $rows = $business->values()->all();
        if ($seekerCount > 0) {
            $oldest = CarbonImmutable::parse((string) $seekers->clone()->min('created_at'));
            $rows[] = [
                'key' => 'job-seekers',
                'type' => 'job_seeker',
                'title' => DashboardFormat::plural($seekerCount, 'new job seeker', 'new job seekers'),
                'detail' => 'Since '.$oldest->setTimezone($now->timezone)->format('D, M j'),
                'age' => DashboardFormat::age($oldest, $now),
            ];
        }

        return [
            'rows' => array_slice($rows, 0, self::LIST_LIMIT),
            'total' => $new->clone()->count(),
        ];
    }
}
