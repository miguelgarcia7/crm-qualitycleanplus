<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Actions\SaveAdjustmentItem;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\AdjustmentItem;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\People\Models\Person;
use App\Support\CompanyTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Billing → Adjustment Items: the ready-made incentives and deductions
 * recruiters pick from on the weekly timesheet and in hour imports
 * (20-domain/adjustments.md). A bounded list, filtered on the client. Items are
 * never deleted (adjustments point at them); making one inactive takes it out
 * of the dropdowns. Uniform and equipment charges never live here: they come
 * from supply requests. Routes gated `adjustments.items.manage`.
 */
class AdjustmentItemController extends Controller
{
    public function index(): Response
    {
        $used = TimeEntryAdjustment::query()
            ->whereNotNull('adjustment_item_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('adjustment_item_id')
            ->select('adjustment_item_id', DB::raw('count(*) as c'))
            ->pluck('c', 'adjustment_item_id');

        $history = Activity::query()
            ->where('subject_type', (new AdjustmentItem)->getMorphClass())
            ->with('causer')
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->groupBy('subject_id');

        return Inertia::render('admin/adjustment-items/index', [
            'items' => AdjustmentItem::query()->orderBy('name')->get()->map(fn (AdjustmentItem $i): array => [
                'id' => $i->id,
                'name' => $i->name,
                'type' => $i->type->value,
                'default_value' => $i->default_value,
                'is_billable' => $i->is_billable,
                'active' => $i->active,
                'used_90' => (int) ($used[$i->id] ?? 0),
                'history' => ($history[$i->id] ?? collect())->map(fn (Activity $a): array => [
                    'id' => $a->id,
                    'at' => CompanyTime::of($a->created_at)?->format('M j, Y'),
                    'text' => $a->description,
                    'by' => $a->causer instanceof Person ? $a->causer->name : null,
                ])->values()->all(),
            ]),
        ]);
    }

    public function store(Request $request, SaveAdjustmentItem $action): RedirectResponse
    {
        $action->handle(null, $this->validated($request), $this->actor($request));

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, AdjustmentItem $adjustmentItem, SaveAdjustmentItem $action): RedirectResponse
    {
        $action->handle($adjustmentItem, $this->validated($request, $adjustmentItem), $this->actor($request));

        return back()->with('success', 'Item saved.');
    }

    /**
     * @return array{name: string, type: string, default_value: int|null, is_billable: bool, active: bool}
     */
    private function validated(Request $request, ?AdjustmentItem $item = null): array
    {
        $v = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('adjustment_items', 'name')->ignore($item?->id)->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'default_amount' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'is_billable' => ['boolean'],
            'active' => ['boolean'],
        ], [
            'name.unique' => 'There is already an item with this name.',
        ]);

        return [
            'name' => trim($v['name']),
            'type' => $v['type'],
            'default_value' => isset($v['default_amount']) ? (int) round((float) $v['default_amount'] * 100) : null,
            'is_billable' => (bool) ($v['is_billable'] ?? false),
            'active' => (bool) ($v['active'] ?? true),
        ];
    }

    private function actor(Request $request): Person
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        return $user;
    }
}
