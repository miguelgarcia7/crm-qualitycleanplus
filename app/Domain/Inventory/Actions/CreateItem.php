<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Creates a catalog item plus its variants. Non-variant items get a single
 * default variant (null size/color) so the rest of the system always works
 * against a variant (ADR-0012).
 */
class CreateItem
{
    /**
     * @param  array{name:string, category_id:int, description?:string|null, has_variants?:bool, reorder_threshold?:int, variants?:list<array{size?:string|null, color?:string|null, sku?:string|null, reorder_threshold?:int}>}  $data
     */
    public function handle(array $data, ?Person $actor): Item
    {
        return DB::transaction(function () use ($data, $actor): Item {
            $category = Category::findOrFail($data['category_id']);
            $hasVariants = $data['has_variants'] ?? $category->has_variants;

            $item = Item::create([
                'name' => $data['name'],
                'category_id' => $category->id,
                'description' => $data['description'] ?? null,
                'has_variants' => $hasVariants,
                'active' => true,
                'created_by' => $actor?->id,
            ]);

            $variants = $hasVariants ? ($data['variants'] ?? []) : [];

            if ($variants === []) {
                $item->variants()->create([
                    'size' => null,
                    'color' => null,
                    'reorder_threshold' => $data['reorder_threshold'] ?? 0,
                ]);
            } else {
                foreach ($variants as $variant) {
                    $item->variants()->create([
                        'size' => $variant['size'] ?? null,
                        'color' => $variant['color'] ?? null,
                        'sku' => $variant['sku'] ?? null,
                        'reorder_threshold' => $variant['reorder_threshold'] ?? 0,
                    ]);
                }
            }

            return $item;
        });
    }
}
