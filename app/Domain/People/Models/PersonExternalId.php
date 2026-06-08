<?php

namespace App\Domain\People\Models;

use App\Domain\PropertyBible\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hotel-specific identifier for a contractor (ADR-0004, people-lifecycle.md).
 * One contractor may have different IDs at multiple import-only hotels, so this is
 * matched on (property_id, external_id) when importing hours (Phase 05).
 */
class PersonExternalId extends Model
{
    protected $table = 'people_external_ids';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'property_id',
        'external_id',
        'source_system',
        'created_by',
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
