<?php

namespace App\Notifications;

use App\Domain\People\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email for a newly created login. Carries a password-reset token
 * so the recipient sets their own password. The link points at the surface
 * the person signs in on: QC Minute for property managers, the back office
 * for internal staff. Mail-only: the recipient has no way to see in-app
 * notifications yet.
 *
 * Queued: the Person row and its role assignments are already committed by
 * the time this is sent, so a mail failure here would leave an account with
 * no way in and no retry — while reporting the invite as failed.
 */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'qcminute'|'backoffice'  $surface
     */
    public function __construct(
        private readonly string $token,
        private readonly string $surface,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Person $notifiable): MailMessage
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $host = $this->surface === 'qcminute' ? config('domains.qcminute') : config('domains.main');
        $path = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email], false);
        $signIn = "{$scheme}://{$host}/login";

        $intro = $this->surface === 'qcminute'
            ? 'An account has been created for you on QC Minute, the portal where you can review weekly timesheets, approve hours, and view invoices for your property.'
            : 'An account has been created for you on the QCP Staffing back office.';

        $mail = new MailMessage;

        // Header must point at the surface this person signs in on, not APP_URL.
        $mail->viewData = ['headerUrl' => "{$scheme}://{$host}"];

        return $mail
            ->subject($this->surface === 'qcminute' ? 'You have been invited to QC Minute' : 'You have been invited to QCP Staffing')
            ->greeting("Hello {$notifiable->name},")
            ->line($intro)
            ->action('Set your password', "{$scheme}://{$host}{$path}")
            ->line("Once your password is set, sign in at {$signIn} using this email address.")
            ->line('For security this link expires after 60 minutes — if it has expired, use "Forgot password" on the sign-in page to request a fresh one.');
    }
}
