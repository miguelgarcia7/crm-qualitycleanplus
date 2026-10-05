<?php

use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\Recruiting\Models\JobApplication;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** @return array<string, string> */
function contactPayload(array $overrides = []): array
{
    return array_merge([
        'contact_first_name' => 'Jamie',
        'contact_last_name' => 'Rivera',
        'contact_email' => 'jamie@example.com',
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

describe('with reCAPTCHA configured', function () {
    beforeEach(function () {
        config([
            'services.recaptcha.site_key' => 'site-key',
            'services.recaptcha.secret_key' => 'secret-key',
        ]);
    });

    it('loads the script and token field on each public form', function (string $path) {
        $this->get(main($path))
            ->assertOk()
            ->assertSee('recaptcha/api.js?render=site-key', false)
            ->assertSee('data-recaptcha-action', false);
    })->with(['/contact-us/job-seekers', '/contact-us/business-inquiries', '/application']);

    it('accepts a contact inquiry Google verifies', function () {
        fakeRecaptcha();

        $this->post(main('/contact-us/job-seekers'), contactPayload())->assertSessionHasNoErrors();

        expect(ContactInquiry::query()->count())->toBe(1);
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'token');
    });

    it('rejects a contact inquiry without a token', function () {
        Http::fake();

        $this->post(main('/contact-us/business-inquiries'), contactPayload(['g-recaptcha-response' => '']))
            ->assertSessionHasErrors('g-recaptcha-response');

        expect(ContactInquiry::query()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('rejects a token Google does not vouch for', function (array $answer) {
        fakeRecaptcha($answer);

        $this->post(main('/contact-us/job-seekers'), contactPayload())
            ->assertSessionHasErrors('g-recaptcha-response');

        expect(ContactInquiry::query()->count())->toBe(0);
    })->with([
        'failed' => [['success' => false]],
        'low score' => [['score' => 0.3]],
        'other action' => [['action' => 'application']],
        'other host' => [['hostname' => 'example.com']],
    ]);

    it('accepts a job application verified for the application action', function () {
        fakeRecaptcha(['action' => 'application']);

        $this->post(main('/application'), applicationPayload(['g-recaptcha-response' => 'token']))
            ->assertRedirect(route('marketing.application.thank-you'));

        expect(JobApplication::query()->count())->toBe(1);
    });

    it('rejects a job application carrying a contact-form token', function () {
        fakeRecaptcha(['action' => 'contact']);

        $this->post(main('/application'), applicationPayload(['g-recaptcha-response' => 'token']))
            ->assertSessionHasErrors('g-recaptcha-response');

        expect(JobApplication::query()->count())->toBe(0);
    });

    it('lets a submission through when Google is unreachable', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->post(main('/contact-us/job-seekers'), contactPayload())->assertSessionHasNoErrors();

        expect(ContactInquiry::query()->count())->toBe(1);
    });
});

it('leaves the forms unguarded while the keys are unset', function () {
    Http::fake();

    $this->get(main('/contact-us/job-seekers'))->assertDontSee('recaptcha/api.js', false);
    $this->post(main('/contact-us/job-seekers'), contactPayload(['g-recaptcha-response' => '']))
        ->assertSessionHasNoErrors();

    Http::assertNothingSent();
});

it('rate-limits the public form posts per visitor', function () {
    foreach (range(1, 10) as $ignored) {
        $this->post(main('/contact-us/job-seekers'), contactPayload())->assertRedirect();
    }

    $this->post(main('/contact-us/business-inquiries'), contactPayload())->assertTooManyRequests();
    $this->post(main('/application'), applicationPayload())->assertTooManyRequests();
});
