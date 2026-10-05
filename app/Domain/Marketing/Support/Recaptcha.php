<?php

namespace App\Domain\Marketing\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scores a Google reCAPTCHA v3 token from a public marketing form. See
 * {@see RecaptchaAssessment} for which outcomes reject and which only flag.
 */
class Recaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public static function enabled(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    /**
     * @param  string  $action  the action the form's script asked Google for
     * @param  string  $host  the host the form was served from
     */
    public function assess(?string $token, string $action, ?string $ip, string $host): RecaptchaAssessment
    {
        if (! self::enabled()) {
            return RecaptchaAssessment::off();
        }

        if (blank($token)) {
            return RecaptchaAssessment::missing();
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (ConnectionException) {
            return $this->unverified('could not reach Google', $action);
        }

        if (! $response->successful()) {
            return $this->unverified('Google returned HTTP '.$response->status(), $action);
        }

        $body = (array) $response->json();

        // "browser-error" here usually means the key's domain list doesn't
        // include this host — a setup problem, not a bot.
        if (($body['success'] ?? false) !== true) {
            return $this->unverified(implode(', ', (array) ($body['error-codes'] ?? ['unknown error'])), $action);
        }

        // A token minted for another form or another site says nothing about
        // this submission, so its score isn't trusted either way.
        if (($body['action'] ?? null) !== $action) {
            return $this->unverified('token was for another form', $action);
        }

        if (($body['hostname'] ?? null) !== $host) {
            return $this->unverified('token was issued on '.($body['hostname'] ?? 'an unknown host'), $action);
        }

        return RecaptchaAssessment::scored((float) ($body['score'] ?? 0));
    }

    private function unverified(string $reason, string $action): RecaptchaAssessment
    {
        Log::warning('reCAPTCHA could not verify a marketing form submission; letting it through.', [
            'action' => $action,
            'reason' => $reason,
        ]);

        return RecaptchaAssessment::unverified($reason);
    }
}
