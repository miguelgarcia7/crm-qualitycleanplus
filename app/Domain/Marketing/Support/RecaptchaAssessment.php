<?php

namespace App\Domain\Marketing\Support;

/**
 * The verdict on one public form submission's reCAPTCHA v3 token.
 *
 * Only two outcomes turn a visitor away: no token at all (a script posting the
 * form directly) and a score Google actually gave below the threshold.
 * Everything that stops a verdict being reached — Google unreachable or
 * erroring, a key not set up for this host, a token minted for another form —
 * lets the submission through as "unverified" and says why, so a key
 * misconfiguration costs a flag on the lead email rather than the lead.
 */
final class RecaptchaAssessment
{
    private function __construct(
        public readonly string $status,
        public readonly ?float $score = null,
        public readonly ?string $reason = null,
    ) {}

    /** Keys not configured — the check is switched off. */
    public static function off(): self
    {
        return new self('off');
    }

    public static function missing(): self
    {
        return new self('missing');
    }

    public static function unverified(string $reason): self
    {
        return new self('unverified', reason: $reason);
    }

    public static function scored(float $score): self
    {
        return new self('scored', score: $score);
    }

    public function rejects(): bool
    {
        return $this->status === 'missing'
            || ($this->status === 'scored' && $this->score < (float) config('services.recaptcha.min_score'));
    }

    /** One line for the lead email. */
    public function summary(): string
    {
        return match ($this->status) {
            'off' => 'Off (reCAPTCHA keys not configured)',
            'missing' => 'Rejected (no token)',
            'unverified' => 'Not verified — '.$this->reason,
            default => sprintf('Looks human — score %.1f', $this->score),
        };
    }
}
