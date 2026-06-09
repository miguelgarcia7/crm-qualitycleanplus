<?php

namespace App\Domain\FieldVisits\Actions;

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Shared\Support\Geofence;
use App\Domain\Time\Support\StoreSelfie;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Opens a recruiter field visit (Phase 07b, ADR-0017). Captures check-in GPS +
 * selfie; the geofence is informational (records `was_inside_geofence` rather than
 * blocking). A recruiter may only have one open visit at a time — the forgot-to-
 * check-out path closes the prior visit first.
 *
 * @phpstan-type CheckInData array{lat?: float|null, lng?: float|null, accuracy?: int|null, gps_status: string, selfie: UploadedFile}
 */
class CheckInRecruiter
{
    /**
     * @param  CheckInData  $data
     */
    public function handle(Person $recruiter, Property $property, array $data): FieldVisit
    {
        if (FieldVisit::query()->where('person_id', $recruiter->id)->open()->exists()) {
            throw ValidationException::withMessages([
                'visit' => 'You already have an open visit. Check out of it first.',
            ]);
        }

        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        $inside = $lat !== null && $lng !== null && Geofence::contains($property, $lat, $lng);

        $visit = FieldVisit::create([
            'person_id' => $recruiter->id,
            'property_id' => $property->id,
            'status' => FieldVisitStatus::Open,
            'check_in_at' => now(),
            'check_in_gps_lat' => $lat,
            'check_in_gps_lng' => $lng,
            'check_in_gps_accuracy_meters' => $data['accuracy'] ?? null,
            'check_in_gps_status' => $data['gps_status'],
            'was_inside_geofence' => $inside,
        ]);

        $visit->update(['check_in_selfie_file_id' => StoreSelfie::for($data['selfie'], $visit, $recruiter->id)->id]);

        return $visit;
    }
}
