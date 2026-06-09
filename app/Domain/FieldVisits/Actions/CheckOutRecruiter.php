<?php

namespace App\Domain\FieldVisits\Actions;

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;

/**
 * Closes a recruiter field visit (Phase 07b, ADR-0017). Captures check-out GPS (no
 * selfie). `$late = true` flags the forgot-to-check-out path (closed from a different
 * property than where the recruiter actually was).
 *
 * @phpstan-type CheckOutData array{lat?: float|null, lng?: float|null, accuracy?: int|null, gps_status: string}
 */
class CheckOutRecruiter
{
    /**
     * @param  CheckOutData  $data
     */
    public function handle(FieldVisit $visit, array $data, bool $late = false): FieldVisit
    {
        $visit->update([
            'status' => FieldVisitStatus::Closed,
            'check_out_at' => now(),
            'check_out_gps_lat' => $data['lat'] ?? null,
            'check_out_gps_lng' => $data['lng'] ?? null,
            'check_out_gps_accuracy_meters' => $data['accuracy'] ?? null,
            'check_out_gps_status' => $data['gps_status'],
            'was_late_close' => $late,
        ]);

        return $visit;
    }
}
