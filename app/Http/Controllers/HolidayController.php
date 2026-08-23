<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Enums\HolidayType;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Support\HolidayDateResolver;
use App\Domain\Time\Actions\RecomputeOpenPeriods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global holiday calendar (Property Bible). Legal holidays are seeded and
 * read-only; custom holidays are month/day dates repeating yearly, validated
 * with checkdate at creation so invoice math can never hit an impossible date
 * (a legacy-app footgun). Deleting a custom holiday detaches it everywhere and
 * recomputes the affected properties' open weeks — frozen invoices are safe by
 * design. Routes gated `bible.holidays.view` / `bible.holidays.edit`.
 */
class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) now()->format('Y');

        return Inertia::render('admin/holidays/index', [
            'holidays' => Holiday::query()
                ->withCount('properties')
                ->orderByRaw("case when type = 'legal' then 0 else 1 end")
                ->orderBy('name')
                ->get()
                ->map(fn (Holiday $h): array => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'type' => $h->type->value,
                    'type_label' => $h->type->label(),
                    'this_year' => HolidayDateResolver::resolve($h, $year, config('app.timezone'))->format('M j'),
                    'properties_count' => $h->properties_count,
                ]),
            'can' => ['edit' => $request->user()?->can('bible.holidays.edit') ?? false],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array{name: string, month: int, day: int} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('holidays', 'name')],
            'month' => ['required', 'integer', 'between:1,12'],
            'day' => [
                'required', 'integer', 'between:1,31',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    // Validate against a non-leap year: Feb 30 / Apr 31 are
                    // impossible, and Feb 29 would silently skip most years.
                    if (! checkdate($request->integer('month'), (int) $value, 2001)) {
                        $fail('That day does not exist in the chosen month.');
                    }
                },
            ],
        ]);

        Holiday::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'type' => HolidayType::Custom,
            'month' => $validated['month'],
            'day' => $validated['day'],
        ]);

        return back()->with('success', 'Holiday created.');
    }

    public function destroy(Holiday $holiday, RecomputeOpenPeriods $recompute): RedirectResponse
    {
        if ($holiday->type === HolidayType::Legal) {
            return back()->with('error', 'Legal holidays are read-only and cannot be deleted.');
        }

        $propertyIds = $holiday->properties()->pluck('properties.id')->all();

        $holiday->delete(); // pivot rows cascade

        $recompute->handle($propertyIds);

        return back()->with('success', 'Holiday deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_');
        $slug = $base;
        $i = 2;

        while (Holiday::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}_{$i}";
            $i++;
        }

        return $slug;
    }
}
