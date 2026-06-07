<?php

namespace App\Domain\Billing\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen per-work-order line on an invoice (ADR-0006).
 *
 * @property int $regular_minutes
 * @property int $overtime_minutes
 * @property int $training_minutes
 * @property int $total_bill
 * @property int $total_payout
 */
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pay_rate' => 'integer',
            'ot_pay_rate' => 'integer',
            'bill_rate' => 'integer',
            'ot_bill_rate' => 'integer',
            'regular_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'holiday_minutes' => 'integer',
            'training_minutes' => 'integer',
            'regular_amount_bill' => 'integer',
            'overtime_amount_bill' => 'integer',
            'holiday_amount_bill' => 'integer',
            'training_amount_bill' => 'integer',
            'total_bill' => 'integer',
            'total_payout' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
