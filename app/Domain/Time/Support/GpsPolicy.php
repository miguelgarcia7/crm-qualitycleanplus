<?php

namespace App\Domain\Time\Support;

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Shared\Support\Geofence;

/**
 * The QR punch GPS ladder (ported from the legacy app's hardest-won lesson):
 * a worker must never be locked out of payroll by their phone. Only a TRUSTED
 * fix that lands OUTSIDE the fence blocks a punch. Every degraded case —
 * permission denied, no fix, accuracy too blunt for the fence, property not
 * configured — lets the punch through, flags it on the time entry, and the
 * caller notifies the property's recruiters. Containment is deliberately not
 * evaluated on an untrusted fix: we do not convict on evidence we don't trust.
 *
 * A fix is trusted when its reported accuracy is within
 * min(qcp.time.gps_accuracy_cap_meters, radius / 2) — half-radius keeps the
 * error circle inside the fence; the cap stops a huge fence from licensing a
 * kilometer-blunt reading.
 */
class GpsPolicy
{
    public const OUTCOME_CLEAN = 'clean';

    public const OUTCOME_FLAGGED = 'flagged';

    public const OUTCOME_BLOCKED = 'blocked';

    /** Client-reported failure reasons we trust enough to record verbatim. */
    private const CLIENT_REASONS = ['permission_denied', 'no_fix'];

    private const REASON_LABELS = [
        'permission_denied' => 'location permission denied',
        'no_fix' => 'no GPS fix',
        'poor_accuracy' => 'poor GPS accuracy',
        'property_unconfigured' => 'property location not set',
        'outside_geofence' => 'outside the geofence',
    ];

    /**
     * @return array{outcome: string, flag_reason: string|null, distance: float|null}
     */
    public static function evaluate(Property $property, ?float $lat, ?float $lng, ?int $accuracy, ?string $clientFailureReason = null): array
    {
        if ($lat === null || $lng === null) {
            $reason = in_array($clientFailureReason, self::CLIENT_REASONS, true) ? $clientFailureReason : 'no_fix';

            return ['outcome' => self::OUTCOME_FLAGGED, 'flag_reason' => $reason, 'distance' => null];
        }

        // Before the accuracy check: the accuracy requirement derives from the fence.
        if ($property->latitude === null || $property->longitude === null) {
            return ['outcome' => self::OUTCOME_FLAGGED, 'flag_reason' => 'property_unconfigured', 'distance' => null];
        }

        if ($accuracy === null || $accuracy > self::accuracyRequiredFor($property)) {
            return ['outcome' => self::OUTCOME_FLAGGED, 'flag_reason' => 'poor_accuracy', 'distance' => null];
        }

        $distance = Geofence::distanceToProperty($property, $lat, $lng);
        if ($distance !== null && $distance > $property->geofence_radius_meters) {
            return ['outcome' => self::OUTCOME_BLOCKED, 'flag_reason' => null, 'distance' => $distance];
        }

        return ['outcome' => self::OUTCOME_CLEAN, 'flag_reason' => null, 'distance' => $distance];
    }

    /** Meters of reported accuracy a fix must beat before we judge it against the fence. */
    public static function accuracyRequiredFor(Property $property): int
    {
        $cap = (int) config('qcp.time.gps_accuracy_cap_meters', 200);

        return min($cap, intdiv((int) $property->geofence_radius_meters, 2));
    }

    public static function reasonLabel(string $reason): string
    {
        return self::REASON_LABELS[$reason] ?? $reason;
    }
}
