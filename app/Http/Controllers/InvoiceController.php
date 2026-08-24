<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\SendInvoice;
use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $user = Auth::user();
        $query = Invoice::query()->with('property:id,name')->latest();

        if ($user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all());
        }

        return Inertia::render('admin/invoices/index', [
            'invoices' => $query->get()->map(fn (Invoice $i): array => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'property' => $i->property?->name,
                'issue_date' => $i->issue_date->toDateString(),
                'total' => $i->total,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
            ]),
        ]);
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

        $action->handle($invoice, $request->user(), $validated['recipient']);

        return back()->with('success', 'Invoice marked as sent.');
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
