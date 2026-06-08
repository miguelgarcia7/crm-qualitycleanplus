<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\BeneficiaryType;
use App\Domain\Inventory\Enums\SupplyRequestStatus;
use App\Domain\People\Models\Person;
use App\Domain\Workflows\Models\Workflow;
use Database\Factories\SupplyRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supply request (ADR-0012), the subject of a `supply_request` workflow.
 *
 * @property SupplyRequestStatus $status
 * @property BeneficiaryType $beneficiary_type
 */
class SupplyRequest extends Model
{
    /** @use HasFactory<SupplyRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'category_id',
        'item_variant_id',
        'beneficiary_type',
        'beneficiary_person_id',
        'quantity',
        'charge_amount',
        'split_payments',
        'needed_by',
        'purpose',
        'notes',
        'proposed_item_name',
        'proposed_description',
        'estimated_cost',
        'requested_by',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplyRequestStatus::class,
            'beneficiary_type' => BeneficiaryType::class,
            'quantity' => 'integer',
            'charge_amount' => 'integer',
            'split_payments' => 'integer',
            'estimated_cost' => 'integer',
            'needed_by' => 'date',
        ];
    }

    public function isNewItem(): bool
    {
        return $this->item_variant_id === null;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'beneficiary_person_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requested_by');
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
