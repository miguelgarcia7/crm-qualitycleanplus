<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\SendInvoice;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class InvoiceController extends Controller
{
    /**
     * Sortable column => the SQL expression behind it. A whitelist, so a
     * hand-edited query string cannot order by an arbitrary column.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'invoice_number' => 'invoices.invoice_number',
        'property' => 'properties.name',
        'issue_date' => 'invoices.issue_date',
        'total' => 'invoices.total',
        'status' => 'invoices.status',
    ];

    private const PER_PAGE = [10, 25, 50];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $user = Auth::user();
        $filters = $this->filters($request);

        $page = $this->listQuery($filters, $user)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('admin/invoices/index', [
            'invoices' => collect($page->items())->map(fn (Invoice $i): array => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'property' => $i->property?->name,
                'issue_date' => $i->issue_date->toDateString(),
                'total' => $i->total,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
            ])->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => $filters,
            // From the enum rather than hardcoded in the view, so adding a
            // status does not mean remembering to edit a dropdown.
            'statuses' => array_map(
                fn (InvoiceStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                InvoiceStatus::cases(),
            ),
            'properties' => $this->propertyOptions($user),
        ]);
    }

    /**
     * The query string, normalised — every value checked against a whitelist.
     *
     * @return array{search: string, status: string, property_id: int|null, sort: string, direction: string, per_page: int}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->string('status');
        $sort = (string) $request->string('sort', 'issue_date');
        $perPage = $request->integer('per_page', 25);

        return [
            'search' => trim((string) $request->string('search')),
            'status' => InvoiceStatus::tryFrom($status) !== null ? $status : '',
            'property_id' => $request->integer('property_id') ?: null,
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'issue_date',
            'direction' => $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * Invoices matching the filters, ordered and scoped to what the viewer may
     * see. The join exists so property sorts and searches in SQL.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function listQuery(array $filters, mixed $user): Builder
    {
        return Invoice::query()
            ->join('properties', 'properties.id', '=', 'invoices.property_id')
            ->when(
                $user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES),
                fn (Builder $q) => $q->whereIn('invoices.property_id', $user->assignedProperties()->pluck('properties.id')->all()),
            )
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('invoices.status', $filters['status']))
            ->when($filters['property_id'] !== null, fn (Builder $q) => $q->where('invoices.property_id', $filters['property_id']))
            ->when($filters['search'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $q->where(fn (Builder $w) => $w
                    ->where('invoices.invoice_number', 'like', $like)
                    ->orWhere('properties.name', 'like', $like));
            })
            ->select('invoices.*')
            ->with('property:id,name')
            ->orderBy(self::SORTS[$filters['sort']], $filters['direction'])
            // A day's invoices all share an issue date — without a stable
            // tiebreaker one can appear on two pages and another on none.
            ->orderBy('invoices.id', 'desc');
    }

    /**
     * Properties for the filter dropdown, limited to the ones this viewer may
     * see invoices for — otherwise the filter itself leaks the client list.
     *
     * @return list<array{id: int, name: string}>
     */
    private function propertyOptions(mixed $user): array
    {
        return Property::query()
            ->whereHas('invoices')
            ->when(
                $user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES),
                fn (EloquentBuilder $q) => $q->whereIn('id', $user->assignedProperties()->pluck('properties.id')->all()),
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Property $p): array => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load('items');
        $user = Auth::user();

        return Inertia::render('admin/invoices/show', [
            'invoice' => $this->payload($invoice),
            'can' => ['send' => $user instanceof Person && $user->can('send', $invoice) && $invoice->status->value !== 'voided'],
        ]);
    }

    public function pdf(Invoice $invoice): HttpResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load('items');

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->download("{$invoice->invoice_number}.pdf");
    }

    public function send(Request $request, Invoice $invoice, SendInvoice $action): RedirectResponse
    {
        $this->authorize('send', $invoice);

        $validated = $request->validate(['recipient' => ['required', 'email']]);

        try {
            $action->handle($invoice, $request->user(), $validated['recipient']);
        } catch (TransportExceptionInterface $e) {
            // The invoice stays unsent — say so rather than leaving the
            // recruiter believing the client has it.
            report($e);

            return back()->withErrors([
                'recipient' => 'The invoice could not be delivered, so it has not been marked as sent. Check the address and try again.',
            ]);
        }

        return back()->with('success', "Invoice emailed to {$validated['recipient']}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'property_snapshot' => $invoice->property_snapshot,
            'invoicer_snapshot' => $invoice->invoicer_snapshot,
            'work_subtotal' => $invoice->work_subtotal,
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'total' => $invoice->total,
            'notification_recipient' => $invoice->notification_recipient,
            'items' => $invoice->items->map(fn ($it): array => [
                'contractor_name' => $it->contractor_name,
                'position_name' => $it->position_name,
                'job_code' => $it->job_code,
                'regular_minutes' => $it->regular_minutes,
                'overtime_minutes' => $it->overtime_minutes,
                'total_bill' => $it->total_bill,
            ]),
            'position_summary' => $this->positionSummary($invoice),
        ];
    }

    /**
     * Per-position rollup of the frozen line items (hours, billed, paid out),
     * stamped with the property's job code — the margin-per-position view in
     * the client's own chart of accounts. Grouped by position name, which the
     * catalog keeps unique.
     *
     * @return list<array<string, mixed>>
     */
    private function positionSummary(Invoice $invoice): array
    {
        return $invoice->items
            ->groupBy('position_name')
            ->map(fn ($items, string $position): array => [
                'position' => $position,
                'job_code' => $items->first()->job_code,
                'regular_minutes' => (int) $items->sum('regular_minutes'),
                'overtime_minutes' => (int) $items->sum('overtime_minutes'),
                'holiday_minutes' => (int) $items->sum('holiday_minutes'),
                'total_bill' => (int) $items->sum('total_bill'),
                'total_payout' => (int) $items->sum('total_payout'),
            ])
            ->values()
            ->all();
    }
}
