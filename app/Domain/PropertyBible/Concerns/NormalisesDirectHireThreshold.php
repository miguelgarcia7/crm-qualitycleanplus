<?php

namespace App\Domain\PropertyBible\Concerns;

/**
 * The direct-hire threshold is entered in hours (how the contract states it) but
 * stored in minutes (how the rest of Time measures worked time). Converts on the
 * way in so exactly one unit crosses the boundary.
 */
trait NormalisesDirectHireThreshold
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normaliseDirectHireThreshold(array $data): array
    {
        if (! array_key_exists('direct_hire_threshold_hours', $data)) {
            return $data;
        }

        $hours = $data['direct_hire_threshold_hours'];
        unset($data['direct_hire_threshold_hours']);

        // Blank means "no contracted value" — fall back to the system default.
        $data['direct_hire_threshold_minutes'] = ($hours === null || $hours === '')
            ? null
            : (int) $hours * 60;

        return $data;
    }
}
