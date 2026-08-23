<?php

namespace App\Domain\Time\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Shared\Models\File;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A raw time event (ADR-0008). Rate snapshots copied from the WO (ADR-0005).
 *
 * @property TimeEntrySource $source
 * @property TimeEntryType $entry_type
 * @property CarbonImmutable|null $start_at_utc
 * @property CarbonImmutable|null $end_at_utc
 */
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'work_order_id',
        'property_id',
        'payroll_period_id',
        'source',
        'clock_method',
        'entry_type',
        'start_at_utc',
        'end_at_utc',
        'duration_minutes',
        'timezone',
        'pay_rate_snapshot',
        'bill_rate_snapshot',
        'ot_pay_rate_snapshot',
        'ot_bill_rate_snapshot',
        'clock_in_gps_lat',
        'clock_in_gps_lng',
        'clock_in_gps_accuracy_meters',
        'clock_in_gps_flag_reason',
        'clock_in_selfie_file_id',
        'clock_out_gps_lat',
        'clock_out_gps_lng',
        'clock_out_gps_accuracy_meters',
        'clock_out_gps_flag_reason',
        'clock_out_selfie_file_id',
        'source_metadata',
        'was_updated',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TimeEntrySource::class,
            'entry_type' => TimeEntryType::class,
            'start_at_utc' => 'datetime',
            'end_at_utc' => 'datetime',
            'duration_minutes' => 'integer',
            'pay_rate_snapshot' => 'integer',
            'bill_rate_snapshot' => 'integer',
            'ot_pay_rate_snapshot' => 'integer',
            'ot_bill_rate_snapshot' => 'integer',
            'clock_in_gps_lat' => 'decimal:7',
            'clock_in_gps_lng' => 'decimal:7',
            'clock_in_gps_accuracy_meters' => 'integer',
            'clock_out_gps_lat' => 'decimal:7',
            'clock_out_gps_lng' => 'decimal:7',
            'clock_out_gps_accuracy_meters' => 'integer',
            'source_metadata' => 'array',
            'was_updated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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
     * @return BelongsTo<File, $this>
     */
    public function clockInSelfie(): BelongsTo
    {
        return $this->belongsTo(File::class, 'clock_in_selfie_file_id');
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function clockOutSelfie(): BelongsTo
    {
        return $this->belongsTo(File::class, 'clock_out_selfie_file_id');
    }
}
