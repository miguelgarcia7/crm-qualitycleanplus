<?php

namespace App\Http\Controllers\Kb;

use App\Domain\KnowledgeBase\Actions\UpdateKbArticle;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\KnowledgeBase\Models\KbArticleVersion;
use App\Domain\KnowledgeBase\Models\KbCategory;
use App\Domain\KnowledgeBase\Models\KbTag;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Enums\FeedbackType;
use App\Domain\Shared\Models\File;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-office KB article management (Phase 08c): authoring with automatic
 * version snapshots, explicit status transitions (publish/unpublish/archive),
 * taxonomy (categories, auto-created tags), role visibility (publishers only),
 * and attachments on the shared polymorphic `files` table. The slug is
 * generated once at creation — it's the route key on both surfaces.
 */
class KbArticleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', KbArticle::class);

        $articles = KbArticle::query()
            ->with(['author:id,name', 'categories:id,name', 'tags:id,name'])
            ->withCount('feedback')
            ->latest('updated_at')
            ->get()
            ->map(fn (KbArticle $a): array => [
                'id' => $a->id,
                'slug' => $a->slug,
                'title' => $a->title,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'version' => $a->version,
                'is_featured' => $a->is_featured,
                'view_count' => $a->view_count,
                'author' => $a->author->name,
                'categories' => $a->categories->pluck('name')->all(),
                'tags' => $a->tags->pluck('name')->all(),
                'feedback_count' => $a->feedback_count,
                'published_at' => $a->published_at?->toDateString(),
                'updated_at' => $a->updated_at?->toDateString(),
            ]);

        return Inertia::render('admin/kb/articles/index', [
            'articles' => $articles,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', KbArticle::class);

        return Inertia::render('admin/kb/articles/form', [
            'article' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', KbArticle::class);

        /** @var Person $actor */
        $actor = $request->user();
        $validated = $this->validated($request);

        $article = KbArticle::create([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'summary' => $validated['summary'],
            'content' => $validated['content'],
            'is_featured' => $validated['is_featured'] ?? false,
            'author_id' => $actor->id,
        ]);

        $this->syncTaxonomy($article, $validated, $actor->can('kb.articles.publish'));

        return redirect()
            ->route('backoffice.kb.articles.show', $article)
            ->with('success', 'Article created as a draft — publish it when ready.');
    }

    public function show(Request $request, KbArticle $article): Response
    {
        $this->authorize('view', $article);

        /** @var Person $actor */
        $actor = $request->user();

        $article->load([
            'author:id,name',
            'lastEditor:id,name',
            'categories:id,name,slug',
            'tags:id,name,slug',
            'roles:id,name',
            'files' => fn ($q) => $q->orderBy('id'),
            'versions.author:id,name',
        ]);

        $votes = $article->feedback()->votes()->get()->groupBy(fn ($f) => $f->type->value);

        return Inertia::render('admin/kb/articles/show', [
            'article' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'content' => $article->content,
                'status' => $article->status->value,
                'status_label' => $article->status->label(),
                'version' => $article->version,
                'is_featured' => $article->is_featured,
                'view_count' => $article->view_count,
                'author' => $article->author->name,
                'last_editor' => $article->lastEditor?->name,
                'categories' => $article->categories->map->only(['id', 'name', 'slug']),
                'tags' => $article->tags->map->only(['id', 'name', 'slug']),
                'roles' => $article->roles->pluck('name'),
                'published_at' => $article->published_at?->format('M j, Y g:i A'),
                'created_at' => $article->created_at?->format('M j, Y g:i A'),
                'updated_at' => $article->updated_at?->format('M j, Y g:i A'),
            ],
            'attachments' => $this->attachmentPayload($article),
            'versions' => $article->versions->map(fn (KbArticleVersion $v): array => [
                'version' => $v->version,
                'title' => $v->title,
                'author' => $v->author->name,
                'change_summary' => $v->change_summary,
                'created_at' => $v->created_at?->format('M j, Y g:i A'),
            ]),
            'feedback_stats' => [
                'helpful' => $votes->get(FeedbackType::Helpful->value)?->count() ?? 0,
                'not_helpful' => $votes->get(FeedbackType::NotHelpful->value)?->count() ?? 0,
                'open' => $article->feedback()->unresolved()->whereNotIn('type', [
                    FeedbackType::Helpful->value, FeedbackType::NotHelpful->value,
                ])->count(),
            ],
            'can' => [
                'update' => $actor->can('update', $article),
                'publish' => $actor->can('publish', $article),
                'delete' => $actor->can('delete', $article),
            ],
        ]);
    }

    public function edit(Request $request, KbArticle $article): Response
    {
        $this->authorize('update', $article);

        $article->load(['categories:id', 'tags:id,name', 'roles:id']);

        return Inertia::render('admin/kb/articles/form', [
            'article' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'content' => $article->content,
                'status' => $article->status->value,
                'status_label' => $article->status->label(),
                'version' => $article->version,
                'is_featured' => $article->is_featured,
                'categories' => $article->categories->pluck('id'),
                'tags' => $article->tags->pluck('name'),
                'roles' => $article->roles->pluck('id'),
            ],
            'attachments' => $this->attachmentPayload($article),
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, KbArticle $article, UpdateKbArticle $action): RedirectResponse
    {
        $this->authorize('update', $article);

        /** @var Person $actor */
        $actor = $request->user();
        $validated = $this->validated($request);

        /** @var string|null $changeSummary */
        $changeSummary = $request->validate([
            'change_summary' => ['nullable', 'string', 'max:500'],
        ])['change_summary'] ?? null;

        $action->handle($article, [
            'title' => $validated['title'],
            'summary' => $validated['summary'],
            'content' => $validated['content'],
            'is_featured' => $validated['is_featured'] ?? false,
        ], $actor, $changeSummary);

        $this->syncTaxonomy($article, $validated, $actor->can('kb.articles.publish'));

        return redirect()
            ->route('backoffice.kb.articles.show', $article)
            ->with('success', "Article updated — now version {$article->version}.");
    }

    public function publish(KbArticle $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        $article->publish();

        return back()->with('success', "\"{$article->title}\" is published.");
    }

    public function unpublish(KbArticle $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        $article->unpublish();

        return back()->with('success', "\"{$article->title}\" is back to draft.");
    }

    public function archive(KbArticle $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        $article->archive();

        return back()->with('success', "\"{$article->title}\" archived.");
    }

    public function destroy(KbArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return redirect()
            ->route('backoffice.kb.articles.index')
            ->with('success', 'Article deleted.');
    }

    public function version(KbArticle $article, int $version): Response
    {
        $this->authorize('view', $article);

        /** @var KbArticleVersion $snapshot */
        $snapshot = $article->versions()->where('version', $version)->with('author:id,name')->firstOrFail();

        return Inertia::render('admin/kb/articles/version', [
            'article' => [
                'slug' => $article->slug,
                'title' => $article->title,
                'version' => $article->version,
            ],
            'snapshot' => [
                'version' => $snapshot->version,
                'title' => $snapshot->title,
                'summary' => $snapshot->summary,
                'content' => $snapshot->content,
                'author' => $snapshot->author->name,
                'change_summary' => $snapshot->change_summary,
                'created_at' => $snapshot->created_at?->format('M j, Y g:i A'),
            ],
        ]);
    }

    public function storeAttachment(Request $request, KbArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);

        /** @var Person $actor */
        $actor = $request->user();

        $request->validate([
            'attachment' => [
                'required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
        ]);

        /** @var UploadedFile $upload */
        $upload = $request->file('attachment');
        $disk = (string) config('filesystems.default');

        File::create([
            'fileable_type' => $article->getMorphClass(),
            'fileable_id' => $article->getKey(),
            'disk' => $disk,
            'path' => $upload->store('kb', $disk),
            'original_name' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getClientMimeType(),
            'size' => $upload->getSize(),
            'uploaded_by' => $actor->id,
        ]);

        return back()->with('success', 'Attachment added.');
    }

    /** Streams an attachment; readers need the article published + role-visible. */
    public function downloadAttachment(Request $request, File $file): StreamedResponse
    {
        /** @var Person $actor */
        $actor = $request->user();

        $article = $file->fileable;
        abort_unless($article instanceof KbArticle, 404);

        abort_unless(
            $actor->can('view', $article) || $actor->can('read', $article),
            403,
        );

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function destroyAttachment(Request $request, File $file): RedirectResponse
    {
        $article = $file->fileable;
        abort_unless($article instanceof KbArticle, 404);

        $this->authorize('update', $article);

        // Soft delete only — the blob stays on disk with the File row as history.
        $file->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /**
     * @return array{title: string, summary: string|null, content: string, is_featured: bool|null, categories: array<int, int>, tags: array<int, string>|null, roles: array<int, int>|null}
     */
    private function validated(Request $request): array
    {
        /** @var array{title: string, summary: string|null, content: string, is_featured: bool|null, categories: array<int, int>, tags: array<int, string>|null, roles: array<int, int>|null} */
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', 'max:200000'],
            'is_featured' => ['nullable', 'boolean'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', 'exists:kb_categories,id'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);
    }

    /**
     * Categories always sync; tags auto-create from free-form names; role
     * visibility only syncs for publishers (others' input is ignored).
     *
     * @param  array{categories: array<int, int>, tags: array<int, string>|null, roles: array<int, int>|null}  $validated
     */
    private function syncTaxonomy(KbArticle $article, array $validated, bool $managesVisibility): void
    {
        $article->categories()->sync($validated['categories']);

        $article->tags()->sync(
            collect($validated['tags'] ?? [])
                ->filter(fn (string $name): bool => trim($name) !== '')
                ->map(fn (string $name): int => KbTag::findOrCreateByName($name)->id)
                ->unique()
                ->values()
                ->all(),
        );

        if ($managesVisibility) {
            $article->roles()->sync($validated['roles'] ?? []);
        }
    }

    /**
     * Form supporting data: category options (tree flattened with depth), tag
     * suggestions, and — for publishers — the role list for visibility.
     *
     * @return array{categoryOptions: Collection<int, array{id: int, name: string, depth: int}>, tagSuggestions: Collection<int, string>, roleOptions: Collection<int, array{id: int|string, name: string}>|null, canManageVisibility: bool}
     */
    private function formOptions(Request $request): array
    {
        /** @var Person $actor */
        $actor = $request->user();
        $canManageVisibility = $actor->can('kb.articles.publish');

        return [
            'categoryOptions' => $this->flattenCategories(),
            'tagSuggestions' => KbTag::query()->orderBy('name')->get()->map(fn (KbTag $t): string => $t->name),
            'roleOptions' => $canManageVisibility
                ? Role::query()->where('name', '!=', 'super_admin')->orderBy('name')->get()
                    ->map(fn (Role $r): array => ['id' => $r->id, 'name' => $r->name])
                : null,
            'canManageVisibility' => $canManageVisibility,
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, depth: int}>
     */
    private function flattenCategories(): Collection
    {
        $byParent = KbCategory::query()->active()->ordered()->get()->groupBy('parent_id');

        $flatten = function (?int $parentId, int $depth) use (&$flatten, $byParent): Collection {
            return ($byParent->get($parentId) ?? collect())
                ->flatMap(fn (KbCategory $c): Collection => collect([[
                    'id' => $c->id,
                    'name' => $c->name,
                    'depth' => $depth,
                ]])->concat($flatten($c->id, $depth + 1)));
        };

        return $flatten(null, 0)->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachmentPayload(KbArticle $article): array
    {
        return $article->files()->orderBy('id')->get()
            ->map(fn (File $f): array => [
                'id' => $f->id,
                'name' => $f->original_name,
                'size' => $f->size,
                'mime_type' => $f->mime_type,
                'is_image' => str_starts_with((string) $f->mime_type, 'image/'),
            ])
            ->all();
    }

    /** The slug is the route key: slugged title, uniquified with a numeric suffix. */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;

        for ($i = 2; KbArticle::query()->withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
