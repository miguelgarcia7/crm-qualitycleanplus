<?php

namespace App\Concerns;

use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for the Property Bible Profile, used by the store +
 * update FormRequests so both stay in sync.
 */
trait PropertyValidationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function propertyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pm_name' => ['nullable', 'string', 'max:255'],
            'pm_phone' => ['nullable', 'string', 'max:32'],
            'main_phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:64'],
            'zip' => ['nullable', 'string', 'max:16'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            // 50m floor: below that, normal phone GPS accuracy can't clear the
            // fence. 5km cap: beyond that the fence stops meaning "on site".
            'geofence_radius_meters' => ['required', 'integer', 'min:50', 'max:5000'],
            'qr_clock_enabled' => [
                'sometimes', 'boolean',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Enabling QR needs a geofence to judge punches against.
                    // Fall back to the stored property when the request omits
                    // latitude (partial update).
                    $property = $this->route('property');
                    $lat = $this->has('latitude')
                        ? $this->input('latitude')
                        : ($property instanceof Property ? $property->latitude : null);
                    if ($value && $lat === null) {
                        $fail('Set the property\'s coordinates before enabling QR clock-in.');
                    }
                },
            ],
            // ISO day-of-week the property's work week ends on (1 = Mon … 7 = Sun).
            'closing_day' => ['nullable', 'integer', 'min:1', 'max:7'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            // Hours from this property's contract before it may hire a
            // contractor directly. Blank = fall back to the system default.
            'direct_hire_threshold_hours' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'status' => ['required', Rule::enum(PropertyStatus::class)],
            'time_source' => ['sometimes', Rule::enum(PropertyTimeSource::class)],
        ];
    }
}
