<?php

namespace App\Domain\Shared\Enums;

/**
 * Kinds of feedback on a feedbackable record (Phase 08c — KB articles first).
 * `helpful` / `not_helpful` are deduplicated votes (one per person per record,
 * changeable); the other three are free-form entries for the admin queue.
 */
enum FeedbackType: string
{
    case Helpful = 'helpful';
    case NotHelpful = 'not_helpful';
    case Suggestion = 'suggestion';
    case Issue = 'issue';
    case Question = 'question';

    public function label(): string
    {
        return match ($this) {
            self::Helpful => 'Helpful',
            self::NotHelpful => 'Not helpful',
            self::Suggestion => 'Suggestion',
            self::Issue => 'Issue',
            self::Question => 'Question',
        };
    }

    /** Votes are deduplicated per person; other types are unlimited. */
    public function isVote(): bool
    {
        return $this === self::Helpful || $this === self::NotHelpful;
    }
}
