<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\EquipmentAssignmentStatus;
use App\Domain\People\Models\Person;
use Carbon\CarbonImmutable;
use Database\Factories\EquipmentAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equipment issued to a person (ADR-0012, ADR-0018).
 *
 * @property EquipmentAssignmentStatus $status
 * @property CarbonImmutable|null $assigned_at
 * @property CarbonImmutable|null $returned_at
 */
class EquipmentAssignment extends Model
{
    /** @use HasFactory<EquipmentAssignmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_variant_id',
        'assigned_to_person_id',
        'source_request_id',
        'quantity',
        'assigned_at',
        'assigned_by',
        'returned_at',
        'returned_by',
        'return_notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EquipmentAssignmentStatus::class,
            'quantity' => 'integer',
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
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
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to_person_id');
    }
}
