<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Actions\RecordStockMovement;
use Database\Factories\ItemVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A stock-keeping variant of an item (ADR-0012). `current_stock` is the running
 * balance kept in sync by {@see RecordStockMovement}.
 */
class ItemVariant extends Model
{
    /** @use HasFactory<ItemVariantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'size',
        'color',
        'sku',
        'current_stock',
        'reorder_threshold',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_stock' => 'integer',
            'reorder_threshold' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** A human label for the variant (e.g. "M / Black" or "Default"). */
    public function label(): string
    {
        $parts = array_filter([$this->size, $this->color]);

        return $parts === [] ? 'Default' : implode(' / ', $parts);
    }

    /** Computed stock status for dashboards (ADR-0012). */
    public function stockStatus(): string
    {
        if ($this->reorder_threshold === 0) {
            return 'not_tracked';
        }
        if ($this->current_stock <= 0) {
            return 'out_of_stock';
        }

        return $this->current_stock <= $this->reorder_threshold ? 'low_stock' : 'in_stock';
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
