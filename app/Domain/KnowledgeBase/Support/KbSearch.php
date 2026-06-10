<?php

namespace App\Domain\KnowledgeBase\Support;

use App\Domain\KnowledgeBase\Models\KbArticle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the KB search constraint (Phase 08c): native FULLTEXT on MySQL /
 * MariaDB (the index only exists there — see the kb_articles migration),
 * LIKE across title/summary/content everywhere else (SQLite test runs; the
 * legacy app queried with LIKE in production too).
 */
class KbSearch
{
    /**
     * @param  Builder<KbArticle>  $query
     */
    public static function apply(Builder $query, string $term): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->whereFullText(['title', 'summary', 'content'], $term);

            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('content', 'like', $like);
        });
    }
}
