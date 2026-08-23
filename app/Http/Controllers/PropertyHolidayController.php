<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\RecomputeOpenPeriods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Per-property holiday opt-in (the Bible's Holidays tab). One transactional
 * sync per save — the legacy app fired one HTTP call per toggled holiday and
 * could end up half-synced. Changing the set recomputes the property's open
 * weeks so bucketing never silently drifts; closed/invoiced weeks are frozen.
 */
class PropertyHolidayController extends Controller
{
    public function sync(Request $request, Property $property, RecomputeOpenPeriods $recompute): RedirectResponse
    {
        $this->authorize('update', $property);

        /** @var array{holiday_ids: list<int>} $validated */
        $validated = $request->validate([
            'holiday_ids' => ['present', 'array'],
            'holiday_ids.*' => ['integer', Rule::exists('holidays', 'id')],
        ]);

        $changed = DB::transaction(function () use ($property, $validated): bool {
            $result = $property->holidays()->sync($validated['holiday_ids']);

            return $result['attached'] !== [] || $result['detached'] !== [];
        });

        if ($changed) {
            $recompute->handle([$property->id]);
        }

        return back()->with('success', 'Holidays updated.');
    }
}
