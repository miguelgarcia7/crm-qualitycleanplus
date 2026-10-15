<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Actions\SavePosition;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global position catalog management (Property Bible). Position names are the
 * one canonical vocabulary used across every property, work order, and report —
 * a rename here propagates everywhere. Positions are never deleted (rates and
 * work orders reference them); deactivate instead. Saving goes through
 * SavePosition, which writes the Audit Log and tells Admin about others' changes. Routes gated
 * `bible.positions.view` / `bible.positions.edit`.
 */
class PositionController extends Controller
{
    public function index(Request $request): Response
    {
        $propertiesCount = DB::table('property_position_rates')
            ->whereNull('deleted_at')
            ->groupBy('position_id')
            ->select('position_id', DB::raw('count(distinct property_id) as c'))
            ->pluck('c', 'position_id');

        $workOrderCount = WorkOrder::query()
            ->groupBy('position_id')
            ->select('position_id', DB::raw('count(*) as c'))
            ->pluck('c', 'position_id');

        return Inertia::render('admin/positions/index', [
            'positions' => Position::query()->orderBy('name')->get()->map(fn (Position $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'notes' => $p->notes,
                'is_active' => $p->is_active,
                'properties_count' => (int) ($propertiesCount[$p->id] ?? 0),
                'work_orders_count' => (int) ($workOrderCount[$p->id] ?? 0),
            ]),
            'can' => ['edit' => $request->user()?->can('bible.positions.edit') ?? false],
        ]);
    }

    public function store(Request $request, SavePosition $action): RedirectResponse
    {
        $action->handle(null, $this->validated($request), $this->actor($request));

        return back()->with('success', 'Position created.');
    }

    public function update(Request $request, Position $position, SavePosition $action): RedirectResponse
    {
        $action->handle($position, $this->validated($request, $position), $this->actor($request));

        return back()->with('success', 'Position updated.');
    }

    private function actor(Request $request): Person
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        return $user;
    }

    /**
     * @return array{name: string, notes: string|null, is_active: bool}
     */
    private function validated(Request $request, ?Position $position = null): array
    {
        /** @var array{name: string, notes: string|null, is_active: bool} */
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('positions', 'name')->ignore($position?->id)->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
