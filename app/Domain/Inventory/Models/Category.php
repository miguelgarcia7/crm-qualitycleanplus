<?php

namespace App\Domain\Inventory\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An inventory category (ADR-0012). Office Supplies / Uniforms / Equipment are
 * seeded; office managers may add more.
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'has_variants',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_variants' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function isUniforms(): bool
    {
        return $this->slug === 'uniforms';
    }

    public function isEquipment(): bool
    {
        return $this->slug === 'equipment';
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
