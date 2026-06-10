<?php

namespace App\Domain\KnowledgeBase\Models;

use Database\Factories\KbCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A KB category (Phase 08c) — hierarchical via `parent_id`; articles may live
 * in multiple categories. `slug` is the route key.
 *
 * @property bool $is_active
 * @property int $sort_order
 */
class KbCategory extends Model
{
    /** @use HasFactory<KbCategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'parent_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<KbCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<KbCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(KbCategory::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<KbArticle, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_category', 'category_id', 'article_id');
    }

    /**
     * @param  Builder<KbCategory>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<KbCategory>  $query
     */
    public function scopeRoot(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<KbCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
