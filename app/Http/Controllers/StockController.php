<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\ManualStockOut;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\ReturnToStock;
use App\Domain\Inventory\Models\ItemVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Direct stock movements on a variant: receive, manual stock-out (ADR-0015),
 * and return-to-stock. Route-gated by the matching inventory.stock.* permission.
 */
class StockController extends Controller
{
    public function receive(Request $request, ItemVariant $variant, ReceiveStock $action): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $action->handle($variant, (int) $validated['quantity'], $validated['reason'], $request->user());

        return back()->with('success', 'Stock received.');
    }

    public function manualOut(Request $request, ItemVariant $variant, ManualStockOut $action): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'recipient_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'recipient_type' => ['nullable', 'in:contractor,staff,external,unspecified'],
        ]);

        $action->handle($variant, (int) $validated['quantity'], $validated['reason'], [
            'recipient_person_id' => isset($validated['recipient_person_id']) ? (int) $validated['recipient_person_id'] : null,
            'recipient_type' => $validated['recipient_type'] ?? null,
        ], $request->user());

        return back()->with('success', 'Stock removed.');
    }

    public function returnStock(Request $request, ItemVariant $variant, ReturnToStock $action): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'related_movement_id' => ['nullable', 'integer', 'exists:stock_movements,id'],
        ]);

        $action->handle(
            $variant,
            (int) $validated['quantity'],
            $validated['reason'],
            isset($validated['related_movement_id']) ? (int) $validated['related_movement_id'] : null,
            $request->user(),
        );

        return back()->with('success', 'Stock returned.');
    }
}
