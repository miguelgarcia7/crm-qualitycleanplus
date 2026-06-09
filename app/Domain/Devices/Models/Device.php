<?php

namespace App\Domain\Devices\Models;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use Carbon\CarbonImmutable;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * A front-desk tablet paired to a property for kiosk clock-in (Phase 07c, ADR-0017).
 * Authenticates via a Sanctum token (the `device` guard); the device pins clock
 * events to {@see $property}.
 *
 * @property bool $is_activated
 * @property CarbonImmutable|null $last_seen_at
 */
class Device extends Authenticatable
{
    /** @use HasFactory<DeviceFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'property_id',
        'name',
        'activation_code',
        'is_activated',
        'app_version',
        'last_seen_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_activated' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /** A fresh 6-char uppercase activation code (no ambiguous characters). */
    public static function newCode(): string
    {
        return Str::upper(Str::random(6));
    }

    public function regenerateCode(): void
    {
        $this->update(['activation_code' => self::newCode()]);
    }

    public function markSeen(?string $appVersion = null): void
    {
        $this->forceFill([
            'last_seen_at' => now(),
            'app_version' => $appVersion ?? $this->app_version,
        ])->save();
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
