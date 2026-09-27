<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use Illuminate\Validation\ValidationException;

/**
 * Records that a client settled an invoice — or undoes a mis-click.
 *
 * Payment is deliberately *not* an InvoiceStatus case. Status tracks the
 * document's lifecycle (draft → invoiced → sent, or voided); payment is
 * orthogonal, since a sent invoice may be paid or not. Legacy conflated them
 * into one status field and lost the ability to say "sent and still unpaid",
 * which is the state anyone chasing money actually cares about.
 *
 * Reversible on purpose, unlike voiding (ADR-0006). Voiding retracts a frozen
 * document and reopens a payroll period; marking paid records a bookkeeping
 * fact about money that arrived, and getting the wrong invoice needs an undo
 * rather than a void-and-reissue.
 */
class MarkInvoicePaid
{
    public function handle(Invoice $invoice, Person $actor): Invoice
    {
        if ($invoice->status === InvoiceStatus::Voided) {
            throw ValidationException::withMessages([
                'invoice' => 'A voided invoice is not owed, so it cannot be marked paid.',
            ]);
        }

        // Idempotent: re-clicking must not rewrite the date someone recorded.
        if ($invoice->paid_at !== null) {
            return $invoice;
        }

        $invoice->update(['paid_at' => now(), 'paid_by' => $actor->id]);

        activity()
            ->performedOn($invoice)
            ->causedBy($actor)
            ->log("Marked invoice {$invoice->invoice_number} paid");

        return $invoice;
    }

    /** Undo — the invoice goes back to owing. */
    public function undo(Invoice $invoice, Person $actor): Invoice
    {
        if ($invoice->paid_at === null) {
            return $invoice;
        }

        $invoice->update(['paid_at' => null, 'paid_by' => null]);

        activity()
            ->performedOn($invoice)
            ->causedBy($actor)
            ->log("Marked invoice {$invoice->invoice_number} unpaid");

        return $invoice;
    }
}
