<?php

namespace App\Http\Controllers\Minute;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Invoices as the client sees them, on QC Minute. Read-only: a property manager
 * views what they were billed and downloads the PDF — generating, sending and
 * voiding all stay in the back office.
 *
 * The payload deliberately omits contractor payout figures. The back-office
 * position summary carries `total_payout` (QCP's margin); that must never reach
 * the property being billed.
 */
class InvoiceController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('minute/invoices/index', [
            'invoices' => $this->scoped()->get()->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'property' => $invoice->property?->name,
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'total' => $invoice->total,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
            ])->values(),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        $invoice->load('items');

        return Inertia::render('minute/invoices/show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'property_snapshot' => $invoice->property_snapshot,
                'invoicer_snapshot' => $invoice->invoicer_snapshot,
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'total' => $invoice->total,
                'items' => $invoice->items->map(fn ($item): array => [
                    'contractor_name' => $item->contractor_name,
                    'position_name' => $item->position_name,
                    'job_code' => $item->job_code,
                    'regular_minutes' => $item->regular_minutes,
                    'overtime_minutes' => $item->overtime_minutes,
                    'total_bill' => $item->total_bill,
                ])->values(),
                'position_summary' => $invoice->items
                    ->groupBy('position_name')
                    ->map(fn ($items, string $position): array => [
                        'position' => $position,
                        'job_code' => $items->first()->job_code,
                        'regular_minutes' => (int) $items->sum('regular_minutes'),
                        'overtime_minutes' => (int) $items->sum('overtime_minutes'),
                        'holiday_minutes' => (int) $items->sum('holiday_minutes'),
                        'total_bill' => (int) $items->sum('total_bill'),
                    ])
                    ->values()
                    ->all(),
            ],
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
     * Issued invoices for the properties this person manages. Drafts are not a
     * client-facing state, so they never appear here.
     *
     * @return Builder<Invoice>
     */
    private function scoped(): Builder
    {
        $user = Auth::user();

        $query = Invoice::query()
            ->with('property:id,name')
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->latest('issue_date');

        if ($user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all());
        }

        return $query;
    }
}
