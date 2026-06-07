<?php

namespace App\Domain\PropertyBible\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\ContractType;
use App\Domain\Shared\Models\File;
use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A property contract (provisional v1 — metadata + one uploaded file).
 * Restricted to super_admin + payroll at the policy layer.
 * See 20-domain/property-bible.md §4 and 90-open/contracts-data-model.md.
 *
 * @property ContractType $type
 * @property CarbonImmutable|null $effective_date
 * @property CarbonImmutable|null $expiration_date
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'name',
        'type',
        'effective_date',
        'expiration_date',
        'uploaded_by',
        'notes',
        'is_active',
        'replaces_contract_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContractType::class,
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return MorphOne<File, $this>
     */
    public function file(): MorphOne
    {
        return $this->morphOne(File::class, 'fileable');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_contract_id');
    }
}
