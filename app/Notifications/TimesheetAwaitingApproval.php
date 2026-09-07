<?php

namespace App\Notifications;

use App\Domain\Billing\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Emails a property manager that a week is waiting on them.
 *
 * Separate from {@see TimesheetStatusChanged} — which stays synchronous so the
 * in-app notice appears the moment the recruiter submits — because this one is
 * queued. A Postmark outage must not fail the submit: by the time mail is
 * attempted the timesheet is already pending and the payroll period already
 * locked, so throwing here would report failure for work that actually
 * happened, and invite the recruiter to try again.
 *
 * Carries a link rather than the hours themselves; the numbers are only
 * meaningful next to the grid, and the recipient has to sign in to act anyway.
 */
class TimesheetAwaitingApproval extends AppNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Timesheet $timesheet) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Timesheets;
    }

    /**
     * Mail only — the in-app notice is sent alongside this by
     * {@see TimesheetStatusChanged}, which does not wait on a queue.
     *
     * @return list<string>
     */
    protected function channels(): array
    {
        return ['mail'];
    }

    /**
     * Not mutable: this is the request that starts the approval, and a week
     * nobody approves is a week nobody invoices. Muting Timesheets still
     * silences the outcome notices, which are informational.
     */
    protected function mutable(): bool
    {
        return false;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $host = (string) config('domains.qcminute');

        $property = $this->timesheet->property->name ?? 'your property';
        $period = $this->timesheet->payrollPeriod;
        $week = $period->week_start->toFormattedDateString().' – '.$period->week_end->toFormattedDateString();

        $mail = new MailMessage;

        // The stock header links to APP_URL — the back office, which a property
        // manager cannot sign in to. Point it at QC Minute instead.
        $mail->viewData = [
            'headerUrl' => "{$scheme}://{$host}",
            'headerName' => (string) config('qcp.invoicer.name', 'QCP Staffing'),
        ];

        return $mail
            ->subject("Timesheet ready for approval — {$property}, week of {$period->week_start->toFormattedDateString()}")
            ->greeting('Hello,')
            ->line("The timesheet for {$property} covering {$week} has been submitted and is waiting for your approval.")
            ->action('Review timesheet', "{$scheme}://{$host}/timesheets/{$this->timesheet->id}")
            ->line('Approving generates the invoice. Declining sends the week back to the recruiter with your reason.')
            ->salutation('Thank you, QCP Staffing');
    }
}
