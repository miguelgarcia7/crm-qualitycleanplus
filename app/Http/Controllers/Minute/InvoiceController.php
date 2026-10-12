<?php

namespace App\Http\Controllers\Minute;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Billing\Support\InvoicePositionSummary;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Invoices as the client sees them, on QC Minute. Read-only: a property manager
 * views what they were billed, whether it's paid, and downloads the PDF —
 * generating, sending and voiding all stay in the back office.
 *
 * The payload carries no contractor payout figures — QCP's margin must never
 * reach the property being billed. That is now structural rather than a strip
 * applied here: {@see InvoicePositionSummary} stopped emitting payout when the
 * back office dropped the column too. Bill rates stay: they are the property's
 * own agreed rates, and the PDF prints them.
 */
class InvoiceController extends Controller
{
    /** Payment filters, as the client reads them. Overdue is derived, never stored. */
    private const FILTERS = ['all', 'open', 'overdue', 'paid'];

    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        /** @var Person $user */
        $user = $request->user();
        $properties = $this->properties($user);

        $status = in_array($request->string('status')->value(), self::FILTERS, true) ? $request->string('status')->value() : 'all';
        $propertyId = $request->integer('property') ?: null;
        $propertyId = $properties->contains('id', $propertyId) ? $propertyId : null;
        $propertyIds = $propertyId === null ? $properties->pluck('id')->all() : [$propertyId];

        $page = $this->filtered($this->scoped($propertyIds), $status)
            ->with('property:id,name', 'payrollPeriod:id,week_start,week_end')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $open = $this->filtered($this->scoped($propertyIds), 'open');
        $overdue = $this->filtered($this->scoped($propertyIds), 'overdue');
        $paidRecently = $this->scoped($propertyIds)->where('paid_at', '>=', now()->subDays(30));
        $nextDue = (clone $open)->whereDate('due_date', '>=', today()->toDateString())->min('due_date');

        return Inertia::render('minute/invoices/index', [
            'invoices' => collect($page->items())->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'property' => $invoice->property?->name,
                'week_start' => $invoice->payrollPeriod?->week_start->toDateString(),
                'week_end' => $invoice->payrollPeriod?->week_end->toDateString(),
                'timesheet_id' => $invoice->timesheet_id,
                'issue_date' => $invoice->issue_date->toDateString(),
                'sent_at' => $invoice->notification_sent_at?->toDateString(),
                'total_minutes' => $invoice->total_regular_minutes + $invoice->total_overtime_minutes + $invoice->total_holiday_minutes + $invoice->total_training_minutes,
                'total' => $invoice->total,
                ...$this->payment($invoice),
            ])->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'counts' => collect(self::FILTERS)
                ->mapWithKeys(fn (string $f): array => [$f => $this->filtered($this->scoped($propertyIds), $f)->count()])
                ->all(),
            'summary' => [
                'open_total' => (int) (clone $open)->sum('total'),
                'open_count' => (clone $open)->count(),
                'next_due' => $nextDue === null ? null : Carbon::parse($nextDue)->toDateString(),
                'overdue_total' => (int) (clone $overdue)->sum('total'),
                'overdue_count' => (clone $overdue)->count(),
                'paid_total' => (int) (clone $paidRecently)->sum('total'),
                'paid_count' => (clone $paidRecently)->count(),
            ],
            'filters' => ['status' => $status, 'property' => $propertyId],
            'properties' => $properties->map(fn (Property $p): array => ['id' => $p->id, 'name' => $p->name])->all(),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        $invoice->load('items', 'payrollPeriod:id,week_start,week_end');

        return Inertia::render('minute/invoices/show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date->toDateString(),
                'sent_at' => $invoice->notification_sent_at?->toDateString(),
                'sent_to' => $invoice->notification_recipient,
                'week_start' => $invoice->payrollPeriod?->week_start->toDateString(),
                'week_end' => $invoice->payrollPeriod?->week_end->toDateString(),
                'timesheet_id' => $invoice->timesheet_id,
                'property_snapshot' => $invoice->propertySnapshotFlat(),
                'invoicer_snapshot' => $invoice->invoicerSnapshotFlat(),
                'subtotal' => $invoice->subtotal,
                'work_subtotal' => $invoice->work_subtotal,
                'adjustment_total' => $invoice->adjustment_total,
                'tax_rate' => (float) $invoice->tax_rate,
                'tax_amount' => $invoice->tax_amount,
                'total' => $invoice->total,
                ...$this->payment($invoice),
                'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                    'contractor_name' => $item->contractor_name,
                    'position_name' => $item->position_name,
                    'job_code' => $item->job_code,
                    'regular_minutes' => $item->regular_minutes,
                    'overtime_minutes' => $item->overtime_minutes,
                    // Holiday and training are part of the amount, so the line
                    // shows them; otherwise hours times rate never adds up.
                    'holiday_minutes' => $item->holiday_minutes,
                    'training_minutes' => $item->training_minutes,
                    // Frozen at issue, so an old invoice always shows the rate it
                    // was actually billed at (ADR-0005).
                    'bill_rate' => $item->bill_rate,
                    'ot_bill_rate' => $item->ot_bill_rate,
                    'holiday_rate' => $item->holidayBillRate(),
                    'training_rate' => $item->trainingBillRate(),
                    'total_bill' => $item->total_bill,
                ])->values(),
                'position_summary' => InvoicePositionSummary::for($invoice),
            ],
            'previewNotice' => config('qcp.invoice.preview_notice'),
        ]);
    }

    public function pdf(Invoice $invoice): HttpResponse
    {
        $this->authorize('view', $invoice);
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        $invoice->load('items');

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->download("{$invoice->invoice_number}.pdf");
    }

    /**
     * Where the invoice stands for the client: paid, due, overdue or voided.
     *
     * @return array<string, mixed>
     */
    private function payment(Invoice $invoice): array
    {
        $open = $invoice->paid_at === null && $invoice->status !== InvoiceStatus::Voided;
        // Due today is still on time, as in the Overdue filter.
        $overdue = $open && $invoice->due_date->lt(today());

        return [
            'voided' => $invoice->status === InvoiceStatus::Voided,
            'due_date' => $invoice->due_date->toDateString(),
            'paid_at' => $invoice->paid_at?->toDateString(),
            'is_overdue' => $overdue,
            'days_overdue' => $overdue ? (int) $invoice->due_date->diffInDays(today(), absolute: true) : null,
            'days_until_due' => $open && ! $overdue ? (int) today()->diffInDays($invoice->due_date, absolute: true) : null,
        ];
    }

    /**
     * The properties this person can see invoices for.
     *
     * @return Collection<int, Property>
     */
    private function properties(Person $user): Collection
    {
        return Property::query()
            ->when(! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES), fn (Builder $q) => $q->assignedTo($user))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Issued invoices for these properties. Drafts are not a client-facing
     * state, so they never appear here.
     *
     * @param  list<int>  $propertyIds
     * @return Builder<Invoice>
     */
    private function scoped(array $propertyIds): Builder
    {
        return Invoice::query()
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereIn('property_id', $propertyIds);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    private function filtered(Builder $query, string $status): Builder
    {
        /** @param Builder<Invoice> $q */
        $open = function (Builder $q): void {
            $q->whereNull('paid_at')->where('status', '!=', InvoiceStatus::Voided->value);
        };

        return match ($status) {
            'open' => $query->where($open),
            'overdue' => $query->where($open)->whereDate('due_date', '<', today()->toDateString()),
            'paid' => $query->whereNotNull('paid_at'),
            default => $query,
        };
    }
}
