<?php

namespace App\Domain\KnowledgeBase\Enums;

/**
 * Lifecycle of a KB article (Phase 08c). Readers only ever see `published`;
 * transitions are explicit clicks (Publish / Unpublish / Archive) — no
 * auto-transitions. Archived articles must be unarchived before editing.
 */
enum KbArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /** Whether an article in this status can be edited (archived cannot). */
    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }
}
