<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apply an edit to a KB article (Phase 08c): snapshot the *current* state into
 * the immutable version history first, then bump `version`, apply the new
 * content, and stamp `last_edited_by`. The slug never changes (route key).
 * Archived articles must be unarchived before editing.
 */
class UpdateKbArticle
{
    /**
     * @param  array{title: string, summary: string|null, content: string, is_featured: bool}  $attributes
     */
    public function handle(KbArticle $article, array $attributes, Person $editor, ?string $changeSummary = null): KbArticle
    {
        if (! $article->status->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Archived articles must be unarchived before editing.',
            ]);
        }

        return DB::transaction(function () use ($article, $attributes, $editor, $changeSummary) {
            $article->snapshotVersion($changeSummary);

            $article->forceFill([
                'title' => $attributes['title'],
                'summary' => $attributes['summary'],
                'content' => $attributes['content'],
                'is_featured' => $attributes['is_featured'],
                'version' => $article->version + 1,
                'last_edited_by' => $editor->id,
            ])->save();

            return $article;
        });
    }
}
