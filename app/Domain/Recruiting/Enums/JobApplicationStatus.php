<?php

namespace App\Domain\Recruiting\Enums;

/**
 * Review state of a job application (Phase 08b-i). Submissions start `submitted`;
 * recruiters/HR move them to `reviewing`, then to `promoted` (the linked person
 * became a contractor) or `rejected`. The promote/reject transitions land in 08b-ii.
 */
enum JobApplicationStatus: string
{
    case Submitted = 'submitted';
    case Reviewing = 'reviewing';
    case Promoted = 'promoted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Reviewing => 'Reviewing',
            self::Promoted => 'Promoted',
            self::Rejected => 'Rejected',
        };
    }

    /** Applications still awaiting a decision. */
    public function isPending(): bool
    {
        return in_array($this, [self::Submitted, self::Reviewing], true);
    }
}
