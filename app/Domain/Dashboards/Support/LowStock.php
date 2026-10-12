<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\PurchaseOrder;

/** Stock at or below its reorder level, for the office manager and the front desk. */
class LowStock
{
    /**
     * Stock at or below its reorder level, lowest first, and what is on order.
     *
     * @return array<string, mixed>
     */
    public function build(int $limit): array
    {
        $low = ItemVariant::query()
            ->where('active', true)
            ->where('reorder_threshold', '>', 0)
            ->whereColumn('current_stock', '<=', 'reorder_threshold');

        return [
            'rows' => $low->clone()
                ->with('item:id,name')
                ->orderByRaw('current_stock * 1.0 / reorder_threshold')
                ->limit($limit)
                ->get()
                ->map(fn (ItemVariant $variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->item->name.($variant->label() !== 'Default' ? ' · '.$variant->label() : ''),
                    'detail' => "{$variant->current_stock} left · reorder at {$variant->reorder_threshold}",
                    // Half the reorder level or less is urgent.
                    'urgent' => $variant->current_stock * 2 <= $variant->reorder_threshold,
                ])
                ->all(),
            'total' => $low->clone()->count(),
            // Not received yet; orders start as drafts and are received from there.
            'onOrder' => PurchaseOrder::query()->whereIn('status', [PurchaseOrderStatus::Draft->value, PurchaseOrderStatus::Ordered->value])->count(),
        ];
    }
}
