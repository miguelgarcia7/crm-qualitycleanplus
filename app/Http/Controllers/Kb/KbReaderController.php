<?php

namespace App\Http\Controllers\Kb;

use App\Domain\KnowledgeBase\Actions\SubmitKbFeedback;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\KnowledgeBase\Models\KbCategory;
use App\Domain\KnowledgeBase\Models\KbTag;
use App\Domain\KnowledgeBase\Support\KbSearch;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Enums\FeedbackType;
use App\Domain\Shared\Models\File;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office KB reader (Phase 08c) — the surface every staff role browses:
 * hub (search + featured + category tree), full-text search with AJAX
 * autosuggest, category/tag browsing, the article page (view counter, related
 * articles, feedback widget). Only published articles visible to the reader's
 * roles appear anywhere here. Routes are gated `can:kb.articles.view`.
 */
class KbReaderController extends Controller
{
    public function home(Request $request): Response
    {
        /** @var Person $reader */
        $reader = $request->user();

        $categories = KbCategory::query()
            ->active()
            ->root()
            ->ordered()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get()
            ->map(fn (KbCategory $c): array => [
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'articles_count' => $this->categoryCount($c, $reader),
                'children' => $c->children->map(fn (KbCategory $child): array => [
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'articles_count' => $this->categoryCount($child, $reader),
                ]),
            ]);

        return Inertia::render('admin/kb/home', [
            'featured' => $this->articleList($this->readable($reader)->where('is_featured', true)->latest('published_at')->limit(6)),
            'recent' => $this->articleList($this->readable($reader)->latest('published_at')->limit(8)),
            'categories' => $categories,
        ]);
    }

    public function search(Request $request): Response
    {
        /** @var Person $reader */
        $reader = $request->user();

        /** @var string $term */
        $term = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q'];

        $query = $this->readable($reader);
        KbSearch::apply($query, $term);

        return Inertia::render('admin/kb/browse', [
            'heading' => "Search: “{$term}”",
            'context' => 'search',
            'q' => $term,
            'articles' => $this->articleList($query->limit(100)),
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        /** @var Person $reader */
        $reader = $request->user();

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $query = $this->readable($reader);
        KbSearch::apply($query, $term);

        return response()->json([
            'results' => $query->limit(8)->get(['id', 'title', 'slug', 'summary'])
                ->map(fn (KbArticle $a): array => [
                    'title' => $a->title,
                    'slug' => $a->slug,
                    'summary' => $a->summary,
                ]),
        ]);
    }

    public function read(Request $request, KbArticle $article): Response
    {
        $this->authorize('read', $article);

        /** @var Person $reader */
        $reader = $request->user();

        $article->increment('view_count');
        $article->load(['author:id,name', 'categories:id,name,slug', 'tags:id,name,slug', 'files' => fn ($q) => $q->orderBy('id')]);

        $related = $this->readable($reader)
            ->whereKeyNot($article->id)
            ->whereHas('categories', fn (Builder $q) => $q->whereIn('kb_categories.id', $article->categories->pluck('id')))
            ->latest('published_at')
            ->limit(5);

        /** @var FeedbackType|null $myVote */
        $myVote = $article->feedback()->votes()->where('person_id', $reader->id)->first()?->type;

        return Inertia::render('admin/kb/read', [
            'article' => [
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'content' => $article->content,
                'author' => $article->author->name,
                'published_at' => $article->published_at?->format('M j, Y'),
                'view_count' => $article->view_count,
                'categories' => $article->categories->map->only(['name', 'slug']),
                'tags' => $article->tags->map->only(['name', 'slug']),
            ],
            'attachments' => $article->files->map(fn (File $f): array => [
                'id' => $f->id,
                'name' => $f->original_name,
                'size' => $f->size,
                'is_image' => str_starts_with((string) $f->mime_type, 'image/'),
            ]),
            'related' => $this->articleList($related),
            'my_vote' => $myVote?->value,
            'can_edit' => $reader->can('kb.articles.edit'),
        ]);
    }

    public function category(Request $request, KbCategory $category): Response
    {
        abort_unless($category->is_active, 404);

        /** @var Person $reader */
        $reader = $request->user();

        $query = $this->readable($reader)
            ->whereHas('categories', fn (Builder $q) => $q->whereKey($category->id))
            ->orderBy('title');

        return Inertia::render('admin/kb/browse', [
            'heading' => $category->name,
            'context' => 'category',
            'description' => $category->description,
            'articles' => $this->articleList($query),
        ]);
    }

    public function tag(Request $request, KbTag $tag): Response
    {
        /** @var Person $reader */
        $reader = $request->user();

        $query = $this->readable($reader)
            ->whereHas('tags', fn (Builder $q) => $q->whereKey($tag->id))
            ->orderBy('title');

        return Inertia::render('admin/kb/browse', [
            'heading' => "Tagged “{$tag->name}”",
            'context' => 'tag',
            'articles' => $this->articleList($query),
        ]);
    }

    public function feedback(Request $request, KbArticle $article, SubmitKbFeedback $action): RedirectResponse
    {
        $this->authorize('read', $article);

        /** @var Person $reader */
        $reader = $request->user();

        /** @var array{type: string, message: string|null} $validated */
        $validated = $request->validate([
            'type' => ['required', Rule::enum(FeedbackType::class)],
            'message' => ['nullable', 'required_unless:type,helpful,not_helpful', 'string', 'max:2000'],
        ]);

        $action->handle(
            $article,
            $reader,
            FeedbackType::from($validated['type']),
            $validated['message'] ?? null,
            $request->headers->get('referer'),
            $request->userAgent(),
        );

        return back()->with('success', 'Thanks — your feedback was recorded.');
    }

    /**
     * Published articles the reader may see.
     *
     * @return Builder<KbArticle>
     */
    private function readable(Person $reader): Builder
    {
        return KbArticle::query()->published()->visibleTo($reader);
    }

    /**
     * @param  Builder<KbArticle>  $query
     * @return list<array<string, mixed>>
     */
    private function articleList(Builder $query): array
    {
        return $query->with('categories:id,name,slug')->get()
            ->map(fn (KbArticle $a): array => [
                'slug' => $a->slug,
                'title' => $a->title,
                'summary' => $a->summary,
                'view_count' => $a->view_count,
                'published_at' => $a->published_at?->format('M j, Y'),
                'categories' => $a->categories->map->only(['name', 'slug'])->all(),
            ])
            ->all();
    }

    private function categoryCount(KbCategory $category, Person $reader): int
    {
        return $this->readable($reader)
            ->whereHas('categories', fn (Builder $q) => $q->whereKey($category->id))
            ->count();
    }
}
