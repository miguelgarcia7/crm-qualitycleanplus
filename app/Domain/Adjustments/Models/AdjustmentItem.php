<?php

namespace App\Domain\Adjustments\Models;

use App\Domain\Adjustments\Enums\AdjustmentType;
use Database\Factories\AdjustmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable manual adjustment template (20-domain/adjustments.md).
 *
 * @property AdjustmentType $type
 */
class AdjustmentItem extends Model
{
    /** @use HasFactory<AdjustmentItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'default_value',
        'type',
        'is_billable',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AdjustmentType::class,
            'default_value' => 'integer',
            'is_billable' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
