<?php

namespace App\Http\Controllers\Kb;

use App\Domain\KnowledgeBase\Models\KbCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * KB category management (Phase 08c) — hierarchical via `parent_id`. Deleting
 * is blocked while articles reference the category; deleting a parent re-roots
 * its children (FK null-on-delete). Routes are gated `kb.categories.manage`.
 */
class KbCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = KbCategory::query()
            ->with('parent:id,name')
            ->withCount('articles')
            ->ordered()
            ->get()
            ->map(fn (KbCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'sort_order' => $c->sort_order,
                'parent_id' => $c->parent_id,
                'parent_name' => $c->parent?->name,
                'is_active' => $c->is_active,
                'articles_count' => $c->articles_count,
            ]);

        return Inertia::render('admin/kb/categories/index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        KbCategory::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, KbCategory $category): RedirectResponse
    {
        $validated = $this->validated($request);

        $this->guardParent($category, $validated['parent_id']);

        // Slug stays stable — it's the route key on the reader surface.
        $category->update($validated);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(KbCategory $category): RedirectResponse
    {
        if ($category->articles()->exists()) {
            return back()->with('error', 'This category has articles — move them first or deactivate the category.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    /**
     * @return array{name: string, description: string|null, parent_id: int|null, sort_order: int|null, is_active: bool|null}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string, description: string|null, parent_id: int|null, sort_order: int|null, is_active: bool|null} */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:kb_categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /** A category cannot be its own parent, nor parented under its own subtree. */
    private function guardParent(KbCategory $category, ?int $parentId): void
    {
        $ancestor = $parentId === null ? null : KbCategory::query()->find($parentId);

        while ($ancestor !== null) {
            if ($ancestor->id === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category cannot live inside its own subtree.',
                ]);
            }

            $ancestor = $ancestor->parent;
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($i = 2; KbCategory::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
