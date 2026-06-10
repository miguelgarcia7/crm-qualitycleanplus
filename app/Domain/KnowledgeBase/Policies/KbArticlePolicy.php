<?php

namespace App\Domain\KnowledgeBase\Policies;

use App\Domain\KnowledgeBase\Enums\KbArticleStatus;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\People\Models\Person;

/**
 * KB article authorization (Phase 08c). Management surface (`viewAny`/`view`/
 * `create`/`update`) follows the `kb.articles.*` permissions; `read` is the
 * reader surface — published + role visibility. `publish` also covers
 * unpublish/archive and role-visibility management (same role set in the
 * permissions matrix). super_admin holds every permission via the seeder and
 * bypasses role visibility inside `isVisibleTo`.
 */
class KbArticlePolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('kb.articles.edit');
    }

    public function view(Person $user, KbArticle $article): bool
    {
        return $user->can('kb.articles.edit');
    }

    /** Reader access: published, role-visible, and holding the read permission. */
    public function read(Person $user, KbArticle $article): bool
    {
        return $user->can('kb.articles.view')
            && $article->status === KbArticleStatus::Published
            && $article->isVisibleTo($user);
    }

    public function create(Person $user): bool
    {
        return $user->can('kb.articles.create');
    }

    public function update(Person $user, KbArticle $article): bool
    {
        return $user->can('kb.articles.edit') && $article->status->isEditable();
    }

    public function publish(Person $user, KbArticle $article): bool
    {
        return $user->can('kb.articles.publish');
    }

    public function delete(Person $user, KbArticle $article): bool
    {
        return $user->can('kb.articles.delete');
    }
}
