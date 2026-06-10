<?php

namespace App\Http\Controllers\Minute;

use App\Domain\KnowledgeBase\Actions\SubmitKbFeedback;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\KnowledgeBase\Support\KbSearch;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Enums\FeedbackType;
use App\Domain\Shared\Models\File;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * QC Minute contractor KB (Phase 08c) — the simplified, mobile-friendly
 * reader: search + article list, article page with helpful/not-helpful votes.
 * No authoring, no category management. Visibility is the same scope as the
 * back office: published + role-visible (articles tagged `contractor` or
 * untagged). Routes are gated `can:kb.articles.view`.
 */
class KbController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Person $reader */
        $reader = $request->user();

        $term = trim((string) $request->query('q', ''));

        $query = KbArticle::query()->published()->visibleTo($reader)->with('categories:id,name');

        if (mb_strlen($term) >= 2) {
            KbSearch::apply($query, $term);
            $query->orderBy('title');
        } else {
            $term = '';
            $query->orderByDesc('is_featured')->latest('published_at');
        }

        return Inertia::render('minute/kb/index', [
            'q' => $term,
            'articles' => $query->limit(100)->get()->map(fn (KbArticle $a): array => [
                'slug' => $a->slug,
                'title' => $a->title,
                'summary' => $a->summary,
                'is_featured' => $a->is_featured,
                'categories' => $a->categories->pluck('name')->all(),
            ]),
        ]);
    }

    public function show(Request $request, KbArticle $article): Response
    {
        $this->authorize('read', $article);

        /** @var Person $reader */
        $reader = $request->user();

        $article->increment('view_count');
        $article->load(['categories:id,name', 'files' => fn ($q) => $q->orderBy('id')]);

        /** @var FeedbackType|null $myVote */
        $myVote = $article->feedback()->votes()->where('person_id', $reader->id)->first()?->type;

        return Inertia::render('minute/kb/show', [
            'article' => [
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'content' => $article->content,
                'published_at' => $article->published_at?->format('M j, Y'),
                'categories' => $article->categories->pluck('name')->all(),
            ],
            'attachments' => $article->files->map(fn (File $f): array => [
                'id' => $f->id,
                'name' => $f->original_name,
                'is_image' => str_starts_with((string) $f->mime_type, 'image/'),
            ]),
            'my_vote' => $myVote?->value,
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

    /** Streams a KB attachment to a reader (same checks as the article). */
    public function downloadAttachment(Request $request, File $file): StreamedResponse
    {
        $article = $file->fileable;
        abort_unless($article instanceof KbArticle, 404);

        $this->authorize('read', $article);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
