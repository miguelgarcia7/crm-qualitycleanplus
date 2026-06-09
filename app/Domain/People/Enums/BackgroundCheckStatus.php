<?php

namespace App\Domain\People\Enums;

/**
 * Background-check state on the onboarding checklist (Phase 08b-ii).
 * `not_required` and `passed` both satisfy the promotion gate.
 */
enum BackgroundCheckStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required',
            self::Pending => 'Pending',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
        };
    }

    /** Whether this state satisfies the promotion gate. */
    public function satisfiesGate(): bool
    {
        return in_array($this, [self::NotRequired, self::Passed], true);
    }
}
