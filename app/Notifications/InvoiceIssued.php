<?php

namespace App\Notifications;

use App\Domain\Billing\Actions\SendInvoice;
use App\Domain\Billing\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invoice email sent to a property. Carries a link rather than a PDF
 * attachment — the recipient views it on QC Minute and downloads the PDF from
 * there if they need one, which keeps the mail small and the document behind a
 * login.
 *
 * Deliberately NOT queued: {@see SendInvoice} marks
 * the invoice sent only once delivery succeeds, so the send has to be
 * synchronous for that guarantee to mean anything.
 */
class InvoiceIssued extends Notification
{
    public function __construct(private readonly Invoice $invoice) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $host = (string) config('domains.qcminute');
        $url = "{$scheme}://{$host}/invoices/{$this->invoice->id}";

        $property = $this->invoice->property_snapshot['name'] ?? 'your property';
        $total = '$'.number_format($this->invoice->total / 100, 2);

        $mail = new MailMessage;

        // The stock header links to APP_URL — the back office, which this
        // recipient cannot sign in to. Point it at QC Minute instead.
        $mail->viewData = [
            'headerUrl' => "{$scheme}://{$host}",
            'headerName' => $this->companyName(),
        ];

        return $mail
            ->subject("Invoice {$this->invoice->invoice_number} from QCP Staffing")
            ->greeting('Hello,')
            ->line("Invoice {$this->invoice->invoice_number} for {$property} is ready to view.")
            ->line("Total due: {$total} by {$this->invoice->due_date->toFormattedDateString()}.")
            ->action('View invoice', $url)
            ->line('You can download a PDF copy from that page. Signing in with your QC Minute account is required.')
            ->salutation('Thank you, QCP Staffing');
    }

    /**
     * The company name as the client knows it — the same identity frozen onto
     * the invoice itself, not APP_NAME (which is the internal app's name).
     */
    private function companyName(): string
    {
        $snapshot = $this->invoice->invoicer_snapshot['name'] ?? null;

        return is_string($snapshot) && trim($snapshot) !== ''
            ? $snapshot
            : (string) config('qcp.invoicer.name', 'QCP Staffing');
    }
}
