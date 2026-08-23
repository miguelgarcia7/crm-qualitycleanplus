<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global department catalog management (Property Bible). Like positions, the
 * catalog keeps one canonical name per department across every property — a
 * rename propagates everywhere. Departments are never deleted
 * (property_departments restricts the FK); deactivate instead. Routes gated
 * `bible.departments.view` / `bible.departments.edit`.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $propertiesCount = DB::table('property_departments')
            ->whereNull('deleted_at')
            ->groupBy('department_id')
            ->select('department_id', DB::raw('count(distinct property_id) as c'))
            ->pluck('c', 'department_id');

        return Inertia::render('admin/departments/index', [
            'departments' => Department::query()->orderBy('name')->get()->map(fn (Department $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'is_active' => $d->is_active,
                'properties_count' => (int) ($propertiesCount[$d->id] ?? 0),
            ]),
            'can' => ['edit' => $request->user()?->can('bible.departments.edit') ?? false],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Department::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        // Slug stays stable across renames — it's an internal identifier only.
        $department->update($this->validated($request, $department));

        return back()->with('success', 'Department updated.');
    }

    /**
     * @return array{name: string, is_active: bool}
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        /** @var array{name: string, is_active: bool} */
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('departments', 'name')->ignore($department?->id)->whereNull('deleted_at'),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Department::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
