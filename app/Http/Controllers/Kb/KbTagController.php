<?php

namespace App\Http\Controllers\Kb;

use App\Domain\KnowledgeBase\Models\KbTag;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * KB tag management (Phase 08c). Tags are mostly auto-created from the article
 * form; this page renames/removes them. `search` is the AJAX autosuggest used
 * by the tag input (gated `kb.articles.edit`); the rest is
 * `kb.categories.manage` (one permission covers categories + tags).
 */
class KbTagController extends Controller
{
    public function index(): Response
    {
        $tags = KbTag::query()
            ->withCount('articles')
            ->orderBy('name')
            ->get()
            ->map(fn (KbTag $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'articles_count' => $t->articles_count,
            ]);

        return Inertia::render('admin/kb/tags/index', [
            'tags' => $tags,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        KbTag::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, KbTag $tag): RedirectResponse
    {
        // Slug stays stable — it's the route key on the reader surface.
        $tag->update(['name' => $this->validated($request)['name']]);

        return back()->with('success', 'Tag renamed.');
    }

    public function destroy(KbTag $tag): RedirectResponse
    {
        $tag->articles()->detach();
        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }

    /** Autosuggest for the article form's tag input. */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $tags = KbTag::query()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($tags);
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string} */
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($i = 2; KbTag::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
