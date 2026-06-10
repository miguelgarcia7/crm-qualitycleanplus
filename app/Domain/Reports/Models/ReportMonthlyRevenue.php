<?php

namespace App\Domain\Reports\Models;

use App\Domain\PropertyBible\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Financial rollup: one row per (property, month), rebuilt from non-voided
 * frozen invoices by RefreshMonthlyRevenue (ADR-0028). Months are keyed by the
 * payroll period's week_start. Derived data — never a source of truth.
 *
 * @property CarbonImmutable $month_start
 */
class ReportMonthlyRevenue extends Model
{
    protected $table = 'report_monthly_revenue';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'month_start',
        'invoice_count',
        'work_subtotal',
        'adjustment_total',
        'tax_amount',
        'invoiced_total',
        'payout_total',
        'last_refreshed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month_start' => 'immutable_date',
            'invoice_count' => 'integer',
            'work_subtotal' => 'integer',
            'adjustment_total' => 'integer',
            'tax_amount' => 'integer',
            'invoiced_total' => 'integer',
            'payout_total' => 'integer',
            'last_refreshed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
