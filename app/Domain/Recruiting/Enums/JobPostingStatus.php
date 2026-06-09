<?php

namespace App\Domain\Recruiting\Enums;

/**
 * Lifecycle of a job posting (Phase 08b-i). Only `published` postings appear on
 * the public job board; `draft` is being prepared, `closed` is retired.
 */
enum JobPostingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Closed => 'Closed',
        };
    }

    /** Whether a posting in this status is visible to the public. */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
