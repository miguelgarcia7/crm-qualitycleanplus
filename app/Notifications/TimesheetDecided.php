<?php

namespace App\Notifications;

use App\Domain\Billing\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Emails the recruiter that a property manager approved or declined their week.
 *
 * The counterpart to {@see TimesheetAwaitingApproval}, and separate from it
 * because the audience is: this one goes to back-office staff, so it links to
 * /admin rather than QC Minute. Queued for the same reason — by the time mail
 * is attempted the decision is committed and, on approval, the invoice already
 * generated; throwing here would report failure for work that succeeded.
 *
 * Each outcome links where the recruiter actually has to go next: the invoice
 * they now have to send, or the grid they have to correct.
 */
class TimesheetDecided extends AppNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Timesheet $timesheet,
        private readonly bool $approved,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Timesheets;
    }

    /**
     * Mail only — {@see TimesheetStatusChanged} posts the in-app notice
     * alongside this, without waiting on a queue.
     *
     * @return list<string>
     */
    protected function channels(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $host = (string) config('domains.main');
        $base = "{$scheme}://{$host}";

        $property = $this->timesheet->property->name ?? 'a property';
        $week = $this->timesheet->payrollPeriod->week_start->toFormattedDateString();

        $mail = new MailMessage;

        // Same header identity as every other QCP email; the URL is the back
        // office, which is where this recipient signs in.
        $mail->viewData = [
            'headerUrl' => $base,
            'headerName' => (string) config('qcp.invoicer.name', 'QCP Staffing'),
        ];

        return $this->approved
            ? $this->approvedMail($mail, $base, $property, $week)
            : $this->declinedMail($mail, $base, $property, $week);
    }

    private function approvedMail(MailMessage $mail, string $base, string $property, string $week): MailMessage
    {
        // Approval generates the invoice (ADR-0006/0007), so send them to it
        // rather than to the list they would have to search.
        $url = $this->timesheet->invoice_id === null
            ? "{$base}/admin/timesheets"
            : "{$base}/admin/invoices/{$this->timesheet->invoice_id}";

        return $mail
            ->subject("Timesheet approved — {$property}, week of {$week}")
            ->greeting('Hello,')
            ->line("The timesheet for {$property} covering the week of {$week} has been approved.")
            ->line('The invoice has been generated and is ready to send.')
            ->action('Open the invoice', $url)
            ->salutation('Thank you, QCP Staffing');
    }

    private function declinedMail(MailMessage $mail, string $base, string $property, string $week): MailMessage
    {
        $weekStart = $this->timesheet->payrollPeriod->week_start->toDateString();

        $mail->subject("Timesheet declined — {$property}, week of {$week}")
            ->greeting('Hello,')
            ->line("The timesheet for {$property} covering the week of {$week} was declined.");

        if (filled($this->timesheet->decline_reason)) {
            // The reason is the whole point of this email.
            $mail->line("Reason given: {$this->timesheet->decline_reason}");
        }

        return $mail
            ->line('The week has been reopened, so you can correct the hours and submit it again.')
            ->action('Open the weekly grid', "{$base}/admin/properties/{$this->timesheet->property_id}/grid?week={$weekStart}")
            ->salutation('Thank you, QCP Staffing');
    }
}
