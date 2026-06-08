<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CreateItem;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use App\Domain\Inventory\Models\ItemVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse + manage the inventory catalog (ADR-0012). Permission-gated at the route
 * (inventory is global, not property-scoped).
 */
class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $items = Item::query()
            ->with(['category:id,name,slug', 'variants'])
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $variants = ItemVariant::query()->where('active', true)->get();
        $tracked = $variants->where('reorder_threshold', '>', 0);

        $user = $request->user();

        return Inertia::render('admin/inventory/index', [
            'categories' => Category::query()->where('active', true)->orderBy('name')
                ->get(['id', 'name', 'slug', 'has_variants']),
            'items' => $items->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category->name,
                'description' => $item->description,
                'variants' => $item->variants->map(fn (ItemVariant $v): array => [
                    'id' => $v->id,
                    'label' => $v->label(),
                    'sku' => $v->sku,
                    'current_stock' => $v->current_stock,
                    'reorder_threshold' => $v->reorder_threshold,
                    'status' => $v->stockStatus(),
                ])->all(),
            ]),
            'stats' => [
                'low_stock' => $tracked->filter(fn (ItemVariant $v): bool => $v->stockStatus() === 'low_stock')->count(),
                'out_of_stock' => $tracked->filter(fn (ItemVariant $v): bool => $v->stockStatus() === 'out_of_stock')->count(),
            ],
            'can' => [
                'create' => $user->can('inventory.items.create'),
                'receive' => $user->can('inventory.stock.receive_direct'),
                'manual_out' => $user->can('inventory.stock.manual_out'),
                'return' => $user->can('inventory.stock.return_to_stock'),
            ],
        ]);
    }

    public function store(Request $request, CreateItem $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_variants' => ['boolean'],
            'reorder_threshold' => ['nullable', 'integer', 'min:0'],
            'variants' => ['array'],
            'variants.*.size' => ['nullable', 'string', 'max:100'],
            'variants.*.color' => ['nullable', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.reorder_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $action->handle($validated, $request->user());

        return back()->with('success', 'Item created.');
    }
}
