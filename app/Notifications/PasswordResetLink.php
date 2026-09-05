<?php

namespace App\Notifications;

use App\Domain\People\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset link, pointed at the surface the recipient actually signs in
 * on.
 *
 * Laravel's stock ResetPassword notification builds its URL with route(), which
 * resolves against APP_URL — the back office. Fortify's auth routes carry no
 * domain constraint so the link technically loads on either host, but it sent
 * property managers and contractors to a domain they cannot sign in to. Same
 * problem the invitation email already solved by carrying its surface.
 */
class PasswordResetLink extends Notification implements ShouldQueue
{
    use Queueable;

    /** Roles whose people sign in on QC Minute rather than the back office. */
    private const QCMINUTE_ROLES = ['property_manager', 'contractor'];

    public function __construct(private readonly string $token) {}

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
        $host = (string) ($notifiable->hasAnyRole(self::QCMINUTE_ROLES)
            ? config('domains.qcminute')
            : config('domains.main'));
        $base = "{$scheme}://{$host}";

        $expires = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $url = "{$base}/reset-password/{$this->token}?email=".urlencode((string) $notifiable->email);

        $mail = new MailMessage;

        // The stock header links to APP_URL too, so it needs the same treatment.
        $mail->viewData = [
            'headerUrl' => $base,
            'headerName' => (string) config('qcp.invoicer.name', 'QCP Staffing'),
        ];

        return $mail
            ->subject('Reset your password')
            ->greeting('Hello,')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset password', $url)
            ->line("This link expires in {$expires} minutes.")
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('Thank you, QCP Staffing');
    }
}
