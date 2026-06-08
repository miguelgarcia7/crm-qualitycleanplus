<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CreatePurchaseOrder;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\PurchaseOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minimal purchase orders (ADR-0012): create a draft + receive in full.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = PurchaseOrder::query()
            ->with(['items.itemVariant.item:id,name'])
            ->latest('id')
            ->get();

        $variants = ItemVariant::query()
            ->where('active', true)
            ->with('item:id,name')
            ->get()
            ->map(fn (ItemVariant $v): array => [
                'id' => $v->id,
                'label' => $v->item->name.' — '.$v->label(),
            ]);

        return Inertia::render('admin/inventory/purchase-orders', [
            'orders' => $orders->map(fn (PurchaseOrder $po): array => [
                'id' => $po->id,
                'status' => $po->status->value,
                'status_label' => $po->status->label(),
                'notes' => $po->notes,
                'received_at' => $po->received_at?->toDateString(),
                'items' => $po->items->map(fn (PurchaseOrderItem $line): array => [
                    'id' => $line->id,
                    'variant' => $line->itemVariant->item->name.' — '.$line->itemVariant->label(),
                    'quantity' => $line->quantity,
                ])->values(),
                'can_receive' => $po->status->canReceive(),
            ]),
            'variants' => $variants->values(),
            'can' => [
                'create' => $request->user()->can('inventory.purchase_orders.create'),
                'receive' => $request->user()->can('inventory.purchase_orders.receive'),
            ],
        ]);
    }

    public function store(Request $request, CreatePurchaseOrder $action): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_variant_id' => ['required', 'integer', 'exists:item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.estimated_unit_cost' => ['nullable', 'integer', 'min:0'],
        ]);

        $action->handle($validated, $request->user());

        return back()->with('success', 'Purchase order created.');
    }

    public function receive(PurchaseOrder $purchaseOrder, ReceivePurchaseOrder $action, Request $request): RedirectResponse
    {
        $action->handle($purchaseOrder, $request->user());

        return back()->with('success', 'Purchase order received.');
    }
}
