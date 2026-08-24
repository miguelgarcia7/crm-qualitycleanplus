<?php

namespace App\Domain\PropertyBible\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The client property's accounting/GL code for a position ("job code") —
 * what must appear next to the position on that property's invoices and
 * payroll exports so their accounting team can post the cost. One row per
 * (property, position). Frozen onto invoice_items at issue (ADR-0006).
 */
class PropertyPositionCode extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'position_id',
        'job_code',
    ];

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
