<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Actions\UpdateTestimonialPhoto;
use App\Domain\Marketing\Enums\TestimonialSource;
use App\Domain\Marketing\Models\Testimonial;
use App\Domain\Shared\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-office management of the marketing home page's testimonials
 * (marketing-site-audit.md, D2). Hidden testimonials stay listed here but never
 * reach the site. Gated `marketing.testimonials.manage`.
 */
class TestimonialController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Testimonial::class);

        $testimonials = Testimonial::query()
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Testimonial $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'quote' => $t->quote,
                'source' => $t->source->value,
                'source_label' => $t->source->label(),
                'rating' => $t->rating,
                'is_active' => $t->is_active,
                'photo_url' => $t->photo_file_id === null ? null : "/admin/testimonials/{$t->id}/photo?v={$t->photo_file_id}",
                'created_at' => $t->created_at?->toIso8601String(),
                'created_at_display' => $t->created_at?->toFormattedDateString(),
            ]);

        return Inertia::render('admin/testimonials/index', [
            'testimonials' => $testimonials,
            'sources' => collect(TestimonialSource::cases())
                ->map(fn (TestimonialSource $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
        ]);
    }

    public function store(Request $request, UpdateTestimonialPhoto $photos): RedirectResponse
    {
        $this->authorize('create', Testimonial::class);

        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data, $photos): void {
            $testimonial = Testimonial::create($data);

            if ($request->hasFile('photo')) {
                $photos->handle($testimonial, $request->file('photo'), $request->user()?->id);
            }
        });

        return back()->with('success', 'Testimonial added.');
    }

    public function update(Request $request, Testimonial $testimonial, UpdateTestimonialPhoto $photos): RedirectResponse
    {
        $this->authorize('update', $testimonial);

        $data = $this->validated($request);

        DB::transaction(function () use ($request, $testimonial, $data, $photos): void {
            $testimonial->update($data);

            if ($request->hasFile('photo')) {
                $photos->handle($testimonial, $request->file('photo'), $request->user()?->id);
            } elseif ($request->boolean('remove_photo')) {
                $photos->remove($testimonial);
            }
        });

        return back()->with('success', 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial, UpdateTestimonialPhoto $photos): RedirectResponse
    {
        $this->authorize('delete', $testimonial);

        DB::transaction(function () use ($testimonial, $photos): void {
            $photos->remove($testimonial);
            $testimonial->delete();
        });

        return back()->with('success', 'Testimonial deleted.');
    }

    /** The photo for the back office — any testimonial, hidden ones included. */
    public function photo(Testimonial $testimonial): StreamedResponse
    {
        $this->authorize('viewAny', Testimonial::class);

        /** @var File|null $file */
        $file = $testimonial->photoFile;
        abort_if($file === null, 404);

        return Storage::disk($file->disk)->response($file->path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * @return array{name: string, quote: string, source: string, rating: int, is_active: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'quote' => ['required', 'string', 'max:1000'],
            'source' => ['required', Rule::enum(TestimonialSource::class)],
            'rating' => ['required', 'integer', 'between:1,5'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'remove_photo' => ['boolean'],
        ]);

        return [
            'name' => $data['name'],
            'quote' => $data['quote'],
            'source' => $data['source'],
            'rating' => (int) $data['rating'],
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
