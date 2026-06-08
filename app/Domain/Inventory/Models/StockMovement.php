<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\RecipientType;
use App\Domain\People\Models\Person;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the unified stock ledger (ADR-0012, ADR-0015).
 *
 * @property MovementType $movement_type
 * @property RecipientType|null $recipient_type
 */
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_variant_id',
        'movement_type',
        'quantity',
        'reason',
        'related_purchase_order_id',
        'related_request_id',
        'related_movement_id',
        'recipient_person_id',
        'recipient_type',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'recipient_type' => RecipientType::class,
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ItemVariant, $this>
     */
    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'recipient_person_id');
    }
}
