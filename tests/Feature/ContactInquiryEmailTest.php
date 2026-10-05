<?php

use App\Domain\Marketing\Models\ContactInquiry;
use App\Notifications\ContactInquiryReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/** @return array<string, string> */
function businessInquiryPayload(array $overrides = []): array
{
    return array_merge([
        'contact_first_name' => 'Dana',
        'contact_last_name' => 'Cole',
        'contact_email' => 'dana@hotel.example',
        'contact_phone' => '214-555-0100',
        'contact_company' => 'Sunrise Hotel',
        'contact_city' => 'Dallas',
        'contact_state' => 'TX',
        'contact_zip' => '75201',
        'contact_inquiry_type' => 'Looking to Hire for Team',
        'contact_message' => 'We need 4 housekeepers for the fall.',
    ], $overrides);
}

it('emails each form type to its own recipients', function () {
    Notification::fake();
    config([
        'qcp.marketing.contact_recipients.job_seeker' => ['jobs@example.com'],
        'qcp.marketing.contact_recipients.business' => ['sales@example.com', 'owner@example.com'],
    ]);

    $this->post(main('/contact-us/business-inquiries'), businessInquiryPayload())->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(
        ContactInquiryReceived::class,
        fn ($n, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['sales@example.com', 'owner@example.com'],
    );
    Notification::assertSentOnDemandTimes(ContactInquiryReceived::class, 1);
});

it('stores the inquiry but sends nothing when its type has no recipients', function () {
    Notification::fake();
    config(['qcp.marketing.contact_recipients.business' => []]);

    $this->post(main('/contact-us/business-inquiries'), businessInquiryPayload())->assertSessionHasNoErrors();

    expect(ContactInquiry::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('reads comma-separated recipients from the environment', function () {
    $original = [$_ENV['MARKETING_BUSINESS_TO'] ?? null, $_SERVER['MARKETING_BUSINESS_TO'] ?? null];
    $_ENV['MARKETING_BUSINESS_TO'] = $_SERVER['MARKETING_BUSINESS_TO'] = ' sales@example.com , owner@example.com,';

    try {
        $config = require config_path('qcp.php');
    } finally {
        [$_ENV['MARKETING_BUSINESS_TO'], $_SERVER['MARKETING_BUSINESS_TO']] = $original;
    }

    expect($config['marketing']['contact_recipients']['business'])->toBe(['sales@example.com', 'owner@example.com']);
});

it('is queued, so a mail outage cannot fail the visitor\'s submit', function () {
    expect(new ContactInquiryReceived(ContactInquiry::factory()->create(), 'Off'))->toBeInstanceOf(ShouldQueue::class);
});

it('renders a real email the recipient can reply to directly', function () {
    $inquiry = ContactInquiry::factory()->create([
        'type' => 'business',
        'first_name' => 'Dana',
        'last_name' => 'Cole',
        'email' => 'dana@hotel.example',
        'company' => 'Sunrise Hotel',
        'message' => 'We need 4 housekeepers. [Click here](https://evil.example)',
    ]);

    // Through the mailer, not just the MailMessage — Blade-layer breakage hides otherwise.
    Notification::route('mail', ['sales@example.com'])
        ->notifyNow(new ContactInquiryReceived($inquiry, 'Looks human — score 0.9'));

    /** @var ArrayTransport $transport */
    $transport = Mail::mailer()->getSymfonyTransport();
    $message = $transport->messages()[0]->getOriginalMessage();
    $html = (string) $message->getHtmlBody();

    expect($message->getSubject())->toBe('Business inquiry from Dana Cole (Sunrise Hotel)')
        ->and($message->getReplyTo()[0]->getAddress())->toBe('dana@hotel.example')
        ->and($message->getTo()[0]->getAddress())->toBe('sales@example.com')
        ->and($html)->toContain('Sunrise Hotel')
        ->and($html)->toContain('We need 4 housekeepers.')
        ->and($html)->toContain('Spam check: Looks human — score 0.9')
        // What the visitor typed stays text, never a link in a staff inbox.
        ->and($html)->not->toContain('href="https://evil.example"');
});
