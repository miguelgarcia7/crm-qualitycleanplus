<?php

namespace App\Domain\Shared\Support;

use App\Domain\PropertyBible\Models\Property;

/**
 * Geofence math for field check-in (Phase 07, ADR-0017). Great-circle distance via
 * the haversine formula; a point is "inside" when its distance to the property is
 * within the property's configured radius (default 300m).
 */
class Geofence
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    /** Great-circle distance between two lat/lng points, in meters. */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * asin(min(1.0, sqrt($a)));
    }

    /** Distance from a point to the property's configured location, or null if the property has no coordinates. */
    public static function distanceToProperty(Property $property, float $lat, float $lng): ?float
    {
        if ($property->latitude === null || $property->longitude === null) {
            return null;
        }

        return self::distanceMeters((float) $property->latitude, (float) $property->longitude, $lat, $lng);
    }

    /** Whether the point lies within the property's geofence radius. */
    public static function contains(Property $property, float $lat, float $lng): bool
    {
        $distance = self::distanceToProperty($property, $lat, $lng);

        return $distance !== null && $distance <= $property->geofence_radius_meters;
    }
}
