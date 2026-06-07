<?php

namespace App\Concerns;

use App\Domain\PropertyBible\Enums\PropertyStatus;
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['required', 'integer', 'min:0', 'max:100000'],
            'closing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'status' => ['required', Rule::enum(PropertyStatus::class)],
        ];
    }
}
