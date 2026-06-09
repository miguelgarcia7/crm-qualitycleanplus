<?php

namespace App\Domain\FieldVisits\Models;

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Shared\Models\File;
use Carbon\CarbonImmutable;
use Database\Factories\FieldVisitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recruiter's visit to a property (Phase 07b, ADR-0017) — accountability logging,
 * not billable time.
 *
 * @property FieldVisitStatus $status
 * @property CarbonImmutable $check_in_at
 * @property CarbonImmutable|null $check_out_at
 * @property bool $was_inside_geofence
 * @property bool $was_late_close
 */
class FieldVisit extends Model
{
    /** @use HasFactory<FieldVisitFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'property_id',
        'status',
        'check_in_at',
        'check_in_gps_lat',
        'check_in_gps_lng',
        'check_in_gps_accuracy_meters',
        'check_in_gps_status',
        'check_in_selfie_file_id',
        'was_inside_geofence',
        'check_out_at',
        'check_out_gps_lat',
        'check_out_gps_lng',
        'check_out_gps_accuracy_meters',
        'check_out_gps_status',
        'was_late_close',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FieldVisitStatus::class,
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_gps_lat' => 'decimal:7',
            'check_in_gps_lng' => 'decimal:7',
            'check_in_gps_accuracy_meters' => 'integer',
            'check_out_gps_lat' => 'decimal:7',
            'check_out_gps_lng' => 'decimal:7',
            'check_out_gps_accuracy_meters' => 'integer',
            'was_inside_geofence' => 'boolean',
            'was_late_close' => 'boolean',
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
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function checkInSelfie(): BelongsTo
    {
        return $this->belongsTo(File::class, 'check_in_selfie_file_id');
    }

    /**
     * @param  Builder<FieldVisit>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', FieldVisitStatus::Open->value);
    }

    /** Minutes between check-in and check-out, or null while still open. */
    public function durationMinutes(): ?int
    {
        if ($this->check_out_at === null) {
            return null;
        }

        return (int) $this->check_in_at->diffInMinutes($this->check_out_at);
    }
}
