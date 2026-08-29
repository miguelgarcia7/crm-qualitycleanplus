<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Notifications\InvoiceIssued;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Emails the invoice to the property and records the send (ADR-0006). The mail
 * carries a link to the invoice on QC Minute, not a PDF attachment.
 *
 * Delivery happens BEFORE the status changes, and deliberately so: if the
 * mailer rejects the send, the exception propagates and the invoice stays
 * unsent rather than claiming otherwise. The reverse order is the failure this
 * replaced — invoices marked sent that nobody ever received.
 *
 * The residual risk is a crash between delivery and the update, which leaves a
 * delivered invoice looking unsent. A recruiter resending it means a duplicate
 * email, which is recoverable; a silently undelivered invoice is not.
 */
class SendInvoice
{
    use LogsPropertyActivity;

    /**
     * @throws TransportExceptionInterface when delivery fails
     */
    public function handle(Invoice $invoice, ?Person $sender, string $recipient): Invoice
    {
        Notification::route('mail', $recipient)->notify(new InvoiceIssued($invoice));

        $invoice->update([
            'status' => InvoiceStatus::InvoiceSent,
            'notification_sent_at' => now(),
            'notification_sent_by' => $sender?->id,
            'notification_recipient' => $recipient,
        ]);

        $invoice->timesheet?->update(['status' => TimesheetStatus::InvoiceSent]);

        $this->logProperty($invoice->property, 'updated', "Invoice {$invoice->invoice_number} emailed to {$recipient}");

        return $invoice;
    }
}
