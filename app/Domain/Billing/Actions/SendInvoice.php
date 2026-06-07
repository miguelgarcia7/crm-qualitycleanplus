<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;

/**
 * Marks an invoice as sent to the property (ADR-0006). PDF is produced on demand
 * by the controller; actual email delivery (Postmark) is deferred — for now this
 * records the send + recipient.
 */
class SendInvoice
{
    use LogsPropertyActivity;

    public function handle(Invoice $invoice, ?Person $sender, string $recipient): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::InvoiceSent,
            'notification_sent_at' => now(),
            'notification_sent_by' => $sender?->id,
            'notification_recipient' => $recipient,
        ]);

        $invoice->timesheet?->update(['status' => TimesheetStatus::InvoiceSent]);

        $this->logProperty($invoice->property, 'updated', "Invoice {$invoice->invoice_number} sent to {$recipient}");

        return $invoice;
    }
}
