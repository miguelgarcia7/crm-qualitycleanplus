<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office job-posting management (Phase 08b-ii) — the postings shown on the
 * public job board. Slug is generated once at creation (it's the public route
 * key) and never changes. Delete is blocked once applications reference the
 * posting — close it instead. Gated `job_postings.manage`.
 */
class JobPostingController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', JobPosting::class);

        $postings = JobPosting::query()
            ->with('property:id,name')
            ->withCount('applications')
            ->latest('id')
            ->get()
            ->map(fn (JobPosting $p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'pay_range' => $p->pay_range,
                'content' => $p->content,
                'hour_start' => $p->hour_start,
                'hour_end' => $p->hour_end,
                'property_id' => $p->property_id,
                'location_label' => $p->location_label,
                'location' => $p->locationName(),
                'applications_count' => $p->applications_count,
            ]);

        return Inertia::render('admin/job-postings/index', [
            'postings' => $postings,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', JobPosting::class);

        $validated = $this->validated($request);

        JobPosting::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
            'status' => JobPostingStatus::Draft,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Posting created as a draft — publish it when ready.');
    }

    public function update(Request $request, JobPosting $posting): RedirectResponse
    {
        $this->authorize('update', $posting);

        $posting->update([
            ...$this->validated($request),
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Posting updated.');
    }

    public function publish(Request $request, JobPosting $posting): RedirectResponse
    {
        $this->authorize('update', $posting);

        $posting->update(['status' => JobPostingStatus::Published, 'updated_by' => $request->user()?->id]);

        return back()->with('success', "\"{$posting->title}\" is live on the job board.");
    }

    public function close(Request $request, JobPosting $posting): RedirectResponse
    {
        $this->authorize('update', $posting);

        $posting->update(['status' => JobPostingStatus::Closed, 'updated_by' => $request->user()?->id]);

        return back()->with('success', "\"{$posting->title}\" removed from the job board.");
    }

    public function destroy(JobPosting $posting): RedirectResponse
    {
        $this->authorize('delete', $posting);

        if ($posting->applications()->exists()) {
            return back()->with('error', 'This posting has applications — close it instead of deleting.');
        }

        $posting->delete();

        return back()->with('success', 'Posting deleted.');
    }

    /**
     * @return array{title: string, pay_range: string|null, content: string|null, hour_start: string|null, hour_end: string|null, property_id: int|null, location_label: string|null}
     */
    private function validated(Request $request): array
    {
        /** @var array{title: string, pay_range: string|null, content: string|null, hour_start: string|null, hour_end: string|null, property_id: int|null, location_label: string|null} */
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'pay_range' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
            'hour_start' => ['nullable', 'date_format:H:i'],
            'hour_end' => ['nullable', 'date_format:H:i'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'location_label' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** The slug is the public route key: slugged title, uniquified with a numeric suffix. */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;

        for ($i = 2; JobPosting::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
