<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Concerns\NormalisesDirectHireThreshold;
use App\Domain\PropertyBible\Models\Property;

class UpdateProperty
{
    use LogsPropertyActivity, NormalisesDirectHireThreshold;

    /** How each field reads in the History section. */
    private const LABELS = [
        'name' => 'name',
        'pm_name' => 'property manager',
        'pm_phone' => 'PM phone',
        'main_phone' => 'main phone',
        'billing_email' => 'billing email',
        'address' => 'address',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip',
        'timezone' => 'timezone',
        'latitude' => 'location',
        'longitude' => 'location',
        'geofence_radius_meters' => 'geofence radius',
        'qr_clock_enabled' => 'QR clock-in',
        'closing_day' => 'week end',
        'tax_rate' => 'tax rate',
        'direct_hire_threshold_minutes' => 'direct-hire threshold',
        'status' => 'status',
        'time_source' => 'time source',
    ];

    /**
     * Saves whichever fields were sent — a Settings card sends only its own —
     * and logs what actually changed, so History reads "Updated tax rate and
     * billing email" rather than the same line for every save.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Property $property, array $data): Property
    {
        $property->update($this->normaliseDirectHireThreshold($data));

        $changed = collect(array_keys($property->getChanges()))
            ->map(fn (string $field): ?string => self::LABELS[$field] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($changed->isNotEmpty()) {
            $this->logProperty($property, 'updated', 'Updated '.$changed->join(', ', ' and '));
        }

        return $property;
    }
}
