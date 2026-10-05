<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Google reCAPTCHA v3 token from a public marketing form. Passes only
 * when Google confirms the token, the score clears `services.recaptcha.min_score`,
 * and the token was issued for this form's action on this host.
 *
 * Implicit, so a missing token fails instead of skipping the rule. A no-op while
 * the keys are unset. If Google can't be reached the submission is let through
 * (logged): losing a real lead costs more than one unchecked form, and the
 * marketing-forms rate limit still applies.
 */
class Recaptcha implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private string $action) {}

    public static function enabled(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::enabled()) {
            return;
        }

        $message = "We couldn't confirm this submission came from a person. Please try again, or call us at 214-271-5595.";

        if (! is_string($value) || $value === '') {
            $fail($message);

            return;
        }

        try {
            $result = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ])->json();
        } catch (ConnectionException $e) {
            Log::warning('reCAPTCHA verification unreachable; accepting submission.', [
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $passed = ($result['success'] ?? false) === true
            && (float) ($result['score'] ?? 0) >= (float) config('services.recaptcha.min_score')
            && ($result['action'] ?? null) === $this->action
            && ($result['hostname'] ?? null) === request()->getHost();

        if (! $passed) {
            $fail($message);
        }
    }
}
