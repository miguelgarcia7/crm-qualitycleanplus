<?php

use App\Domain\Billing\Actions\SendInvoice;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\PropertyBible\Models\Property;
use App\Notifications\InvoiceIssued;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Swap in a transport that refuses every message — Postmark with a bad key, an
 * unverified sender, or an outage. Fails at the real transport layer so the
 * whole notification → channel → mailer path is exercised.
 */
function useFailingMailer(): void
{
    Mail::extend('failing', fn (): TransportInterface => new class implements TransportInterface
    {
        public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
        {
            throw new TransportException('Postmark rejected the message');
        }

        public function __toString(): string
        {
            return 'failing';
        }
    });

    config()->set('mail.mailers.failing', ['transport' => 'failing']);
    config()->set('mail.default', 'failing');

    Mail::forgetMailers();
}

it('emails a link to the invoice on QC Minute rather than attaching a PDF', function () {
    Notification::fake();

    $invoice = Invoice::factory()->create(['property_id' => Property::factory()]);

    app(SendInvoice::class)->handle($invoice, person('recruiter'), 'billing@hotel.test');

    Notification::assertSentOnDemand(InvoiceIssued::class, function (InvoiceIssued $notification, array $channels, object $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'billing@hotel.test'
            && str_contains($mail->actionUrl, config('domains.qcminute'))
            && str_contains($mail->actionUrl, '/invoices/')
            && $mail->attachments === [];
    });
});

it('points the mail header at QC Minute, not the back office', function () {
    Notification::fake();

    $invoice = Invoice::factory()->create([
        'property_id' => Property::factory(),
        'invoicer_snapshot' => ['name' => 'Quality Cleaning Plus LLC'],
    ]);

    app(SendInvoice::class)->handle($invoice, person('recruiter'), 'billing@hotel.test');

    Notification::assertSentOnDemand(InvoiceIssued::class, function (InvoiceIssued $n, array $channels, object $notifiable): bool {
        $mail = $n->toMail($notifiable);

        // The stock header links to APP_URL — the back office, which this
        // recipient cannot sign in to.
        return $mail->viewData['headerUrl'] === 'https://'.config('domains.qcminute')
            && ! str_contains($mail->viewData['headerUrl'], (string) config('domains.main'))
            && $mail->viewData['headerName'] === 'Quality Cleaning Plus LLC';
    });
});

it('renders the whole email on the QC Minute host', function () {
    $invoice = Invoice::factory()->create(['property_id' => Property::factory()]);
    $notification = new InvoiceIssued($invoice);

    $rendered = (string) $notification->toMail(new AnonymousNotifiable)->render();

    expect($rendered)->toContain(config('domains.qcminute'))
        ->and($rendered)->not->toContain(config('domains.main'));
});

it('marks the invoice sent once delivery succeeds', function () {
    Notification::fake();

    $invoice = Invoice::factory()->create(['property_id' => Property::factory()]);

    app(SendInvoice::class)->handle($invoice, person('recruiter'), 'billing@hotel.test');

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::InvoiceSent)
        ->and($invoice->notification_recipient)->toBe('billing@hotel.test')
        ->and($invoice->notification_sent_at)->not->toBeNull();
});

it('leaves the invoice unsent when the mailer rejects it', function () {
    $invoice = Invoice::factory()->create([
        'property_id' => Property::factory(),
        'status' => InvoiceStatus::Invoiced,
    ]);

    // Stand in for Postmark refusing the send (bad key, unverified sender, outage).
    useFailingMailer();

    expect(fn () => app(SendInvoice::class)->handle($invoice, person('recruiter'), 'billing@hotel.test'))
        ->toThrow(TransportException::class);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Invoiced)
        ->and($invoice->notification_sent_at)->toBeNull()
        ->and($invoice->notification_recipient)->toBeNull();
});

it('tells the recruiter the send failed instead of reporting success', function () {
    $property = Property::factory()->create();
    $invoice = Invoice::factory()->create(['property_id' => $property->id, 'status' => InvoiceStatus::Invoiced]);

    useFailingMailer();

    $this->actingAs(person('office_manager'))
        ->from(main("/admin/invoices/{$invoice->id}"))
        ->post(main("/admin/invoices/{$invoice->id}/send"), ['recipient' => 'billing@hotel.test'])
        ->assertRedirect(main("/admin/invoices/{$invoice->id}"))
        ->assertSessionHasErrors('recipient');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Invoiced);
});
