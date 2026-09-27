<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Support\InvoiceSnapshot;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A frozen invoice billed to a property (ADR-0006).
 *
 * @property InvoiceStatus $status
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $due_date
 * @property CarbonImmutable|null $paid_at
 * @property int|null $paid_by
 * @property array<string, mixed> $property_snapshot
 * @property array<string, mixed> $invoicer_snapshot
 * @property int $work_subtotal
 * @property int $subtotal
 * @property int $tax_amount
 * @property int $total
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    /**
     * The snapshots in this app's flat shape — see {@see InvoiceSnapshot}.
     * Always use these for display; the raw attributes vary by origin.
     *
     * @return array<string, mixed>
     */
    public function propertySnapshotFlat(): array
    {
        return InvoiceSnapshot::flatten($this->property_snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    public function invoicerSnapshotFlat(): array
    {
        return InvoiceSnapshot::flatten($this->invoicer_snapshot);
    }

    /**
     * Unpaid and past due. Derived rather than stored — a stored flag would
     * need a nightly job to stay true, and would be wrong between runs.
     */
    public function isOverdue(): bool
    {
        return $this->paid_at === null
            && $this->status !== InvoiceStatus::Voided
            && $this->due_date->isPast();
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'property_snapshot' => 'array',
            'invoicer_snapshot' => 'array',
            'tax_rate' => 'decimal:4',
            'work_subtotal' => 'integer',
            'adjustment_total' => 'integer',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'total_regular_minutes' => 'integer',
            'total_overtime_minutes' => 'integer',
            'total_holiday_minutes' => 'integer',
            'total_training_minutes' => 'integer',
            'frozen_at' => 'datetime',
            'notification_sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Frozen invoices generated but not yet sent to the property.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeAwaitingSend(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Invoiced->value);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'paid_by');
    }

    /**
     * @return BelongsTo<Timesheet, $this>
     */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
