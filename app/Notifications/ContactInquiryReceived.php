<?php

namespace App\Notifications;

use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\Marketing\Support\RecaptchaAssessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a marketing-site contact lead (job seeker or business) to the
 * addresses in `qcp.marketing.contact_recipients` — sent on demand, not to a
 * Person, so it skips {@see AppNotification}'s preferences.
 *
 * Queued: the inquiry is already stored before mail is attempted, so a mail
 * outage retries on its own instead of failing the visitor's submit. Reply-to
 * is the visitor, so answering the email answers them.
 */
class ContactInquiryReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $spamCheck  {@see RecaptchaAssessment::summary()}
     * @param  string  $siteLocale  the site language the visitor used
     */
    public function __construct(
        private readonly ContactInquiry $inquiry,
        private readonly string $spamCheck,
        private readonly string $siteLocale = 'en',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry;
        $name = trim($inquiry->first_name.' '.$inquiry->last_name);
        $kind = $inquiry->type === 'business' ? 'Business inquiry' : 'Job seeker inquiry';

        $mail = (new MailMessage)
            ->subject("{$kind} from {$name}".(filled($inquiry->company) ? " ({$inquiry->company})" : ''))
            ->replyTo($inquiry->email, $name)
            ->greeting($kind)
            ->line("{$name} sent this through the website contact form. Reply to this email to answer them directly.");

        // Staff read English; flag a Spanish-site lead so the reply can be in Spanish.
        if ($this->siteLocale === 'es') {
            $mail->line('**Language:** Spanish — sent from the Spanish site.');
        }

        $location = trim(implode(', ', array_filter([$inquiry->city, trim($inquiry->state.' '.$inquiry->zip)])));

        foreach ([
            'Name' => $name,
            'Email' => $inquiry->email,
            'Phone' => $inquiry->phone,
            'Best time to call' => $inquiry->call_back_time,
            'Company' => $inquiry->company,
            'Inquiry type' => $inquiry->inquiry_type,
            'Address' => $inquiry->address,
            'City / State / Zip' => $location,
            'Message' => $inquiry->message,
        ] as $label => $value) {
            if (filled($value)) {
                $mail->line("**{$label}:** ".$this->plain($value));
            }
        }

        return $mail
            ->line("Spam check: {$this->spamCheck}")
            ->action('Open in the back office', route('backoffice.inquiries.index', ['open' => $inquiry->id]))
            ->salutation('Quality Cleaning Plus website');
    }

    /**
     * Mail lines are rendered as Markdown. What a visitor typed must not become
     * a link or formatting in a staff inbox. Escapes only what can produce
     * either mid-line (links, autolinks, emphasis, code), so addresses and
     * phone numbers stay readable in the plain-text part.
     */
    private function plain(string $value): string
    {
        return (string) preg_replace('/([\\\\`*_\[\]<>])/', '\\\\$1', $value);
    }
}
