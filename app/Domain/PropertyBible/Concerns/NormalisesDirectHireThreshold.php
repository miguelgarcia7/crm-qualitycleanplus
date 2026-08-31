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
     * @param  bool  $blankMeansInherit  true for work orders, whose column is NOT
     *                                   NULL and whose blank means "take the
     *                                   property's value" — the key is dropped
     *                                   so WorkOrder's creating hook resolves
     *                                   it. False for properties, where null is
     *                                   itself meaningful ("no contracted
     *                                   value, use the system default").
     * @return array<string, mixed>
     */
    protected function normaliseDirectHireThreshold(array $data, bool $blankMeansInherit = false): array
    {
        if (! array_key_exists('direct_hire_threshold_hours', $data)) {
            return $data;
        }

        $hours = $data['direct_hire_threshold_hours'];
        unset($data['direct_hire_threshold_hours']);

        if ($hours === null || $hours === '') {
            if ($blankMeansInherit) {
                return $data;
            }

            $data['direct_hire_threshold_minutes'] = null;

            return $data;
        }

        $data['direct_hire_threshold_minutes'] = (int) $hours * 60;

        return $data;
    }
}
