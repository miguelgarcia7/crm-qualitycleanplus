<?php

use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\Recruiting\Models\JobApplication;
use App\Notifications\ContactInquiryReceived;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** @return array<string, string> */
function contactPayload(array $overrides = []): array
{
    return array_merge([
        'contact_first_name' => 'Jamie',
        'contact_last_name' => 'Rivera',
        'contact_email' => 'jamie@example.com',
        'contact_phone' => '214-555-0100',
        'g-recaptcha-response' => 'token',
    ], $overrides);
}

/** Fake Google's siteverify answer; defaults are a clean pass for the contact form. */
function fakeRecaptcha(array $overrides = []): void
{
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(array_merge([
        'success' => true,
        'score' => 0.9,
        'action' => 'contact',
        'hostname' => config('domains.main'),
    ], $overrides))]);
}

/** The spam-check line the lead email was sent with. */
function sentSpamCheck(): string
{
    $line = null;

    Notification::assertSentOnDemand(ContactInquiryReceived::class, function (ContactInquiryReceived $n) use (&$line): bool {
        $line = collect($n->toMail(new AnonymousNotifiable)->introLines)
            ->first(fn (string $l): bool => str_starts_with($l, 'Spam check:'));

        return true;
    });

    return (string) $line;
}

describe('with reCAPTCHA configured', function () {
    beforeEach(function () {
        config([
            'services.recaptcha.site_key' => 'site-key',
            'services.recaptcha.secret_key' => 'secret-key',
            'qcp.marketing.contact_recipients.job_seeker' => ['leads@example.com'],
        ]);
        Notification::fake();
    });

    it('loads the script and token field on each public form', function (string $path) {
        $this->get(main($path))
            ->assertOk()
            ->assertSee('recaptcha/api.js?render=site-key', false)
            ->assertSee('data-recaptcha-action', false);
    })->with(['/contact-us/job-seekers', '/contact-us/business-inquiries', '/application']);

    it('accepts a token Google scores as human and says so in the lead email', function () {
        fakeRecaptcha();

        $this->post(main('/contact-us/job-seekers'), contactPayload())->assertSessionHasNoErrors();

        expect(ContactInquiry::query()->count())->toBe(1)
            ->and(sentSpamCheck())->toBe('Spam check: Looks human — score 0.9');
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'token');
    });

    it('rejects a submission with no token — a script posting the form directly', function () {
        Http::fake();

        $this->post(main('/contact-us/job-seekers'), contactPayload(['g-recaptcha-response' => '']))
            ->assertSessionHasErrors('g-recaptcha-response');

        expect(ContactInquiry::query()->count())->toBe(0);
        Http::assertNothingSent();
        Notification::assertNothingSent();
    });

    it('rejects a token Google scored below the threshold', function () {
        fakeRecaptcha(['score' => 0.3]);

        $this->post(main('/contact-us/job-seekers'), contactPayload())
            ->assertSessionHasErrors('g-recaptcha-response');

        expect(ContactInquiry::query()->count())->toBe(0);
    });

    it('lets through, flagged, what Google could not vouch for either way', function (array $answer, string $flag) {
        fakeRecaptcha($answer);

        $this->post(main('/contact-us/job-seekers'), contactPayload())->assertSessionHasNoErrors();

        expect(ContactInquiry::query()->count())->toBe(1)
            ->and(sentSpamCheck())->toBe("Spam check: Not verified — {$flag}");
    })->with([
        'key not set up for this host' => [['success' => false, 'error-codes' => ['browser-error']], 'browser-error'],
        'token for another form' => [['action' => 'application'], 'token was for another form'],
        'token from another host' => [['hostname' => 'example.com'], 'token was issued on example.com'],
    ]);

    it('lets a submission through, flagged, when Google is unreachable', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->post(main('/contact-us/job-seekers'), contactPayload())->assertSessionHasNoErrors();

        expect(sentSpamCheck())->toBe('Spam check: Not verified — could not reach Google');
    });

    it('does not spend a Google call on a form going back for other errors', function () {
        Http::fake();

        $this->post(main('/contact-us/job-seekers'), contactPayload(['contact_email' => '']))
            ->assertSessionHasErrors('contact_email')
            ->assertSessionDoesntHaveErrors('g-recaptcha-response');

        Http::assertNothingSent();
    });

    it('accepts a job application scored for the application action', function () {
        fakeRecaptcha(['action' => 'application']);

        $this->post(main('/application'), applicationPayload(['g-recaptcha-response' => 'token']))
            ->assertRedirect(route('marketing.application.thank-you'));

        expect(JobApplication::query()->count())->toBe(1);
    });

    it('rejects a job application with no token', function () {
        Http::fake();

        $this->post(main('/application'), applicationPayload())
            ->assertSessionHasErrors('g-recaptcha-response');

        expect(JobApplication::query()->count())->toBe(0);
    });
});

it('leaves the forms unguarded while the keys are unset', function () {
    Http::fake();
    Notification::fake();
    config(['qcp.marketing.contact_recipients.job_seeker' => ['leads@example.com']]);

    $this->get(main('/contact-us/job-seekers'))->assertDontSee('recaptcha/api.js', false);
    $this->post(main('/contact-us/job-seekers'), contactPayload(['g-recaptcha-response' => '']))
        ->assertSessionHasNoErrors();

    Http::assertNothingSent();
    expect(sentSpamCheck())->toBe('Spam check: Off (reCAPTCHA keys not configured)');
});

it('rate-limits the public form posts per visitor', function () {
    foreach (range(1, 10) as $ignored) {
        $this->post(main('/contact-us/job-seekers'), contactPayload())->assertRedirect();
    }

    $this->post(main('/contact-us/business-inquiries'), contactPayload())->assertTooManyRequests();
    $this->post(main('/application'), applicationPayload())->assertTooManyRequests();
});
