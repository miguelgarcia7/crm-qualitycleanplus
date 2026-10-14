<?php

namespace App\Domain\FieldVisits\Support;

use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Support\Geofence;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

/**
 * One visit for the side panel, on the Field Visits list and the hotel's
 * Visits tab: the selfie, where the recruiter stood against the hotel's
 * check-in area, the visit's History, and what the viewer may do with it.
 */
class VisitPanel
{
    /**
     * @return array<string, mixed>
     */
    public function build(FieldVisit $visit, Person $user): array
    {
        $visit->loadMissing(['person:id,name', 'property', 'closedBy:id,name']);
        $property = $visit->property;
        $tz = $property?->timezone ?: CompanyTime::zone();
        $in = $visit->check_in_at->setTimezone($tz);
        $out = $visit->check_out_at?->setTimezone($tz);

        return [
            'id' => $visit->id,
            'recruiter' => $visit->person?->name,
            'recruiter_id' => $visit->person_id,
            'property' => $property?->name,
            'property_id' => $visit->property_id,
            'timezone' => $tz,
            'open' => $out === null,
            'open_hours' => $out === null ? (int) $visit->check_in_at->diffInHours(now()) : null,
            'in' => $in->format('D, M j · g:i A'),
            'out' => $out === null ? null : ($out->isSameDay($in) ? $out->format('g:i A') : $out->format('D, M j · g:i A')),
            'minutes' => $visit->durationMinutes(),
            'off_site' => ! $visit->was_inside_geofence,
            'late' => $visit->was_late_close,
            'covering' => $visit->was_covering,
            'closed_by' => $visit->closedBy?->name,
            'notes' => $visit->notes,
            'check_in' => $this->place($visit->check_in_gps_status, $visit->check_in_gps_lat, $visit->check_in_gps_lng, $visit->check_in_gps_accuracy_meters, $visit),
            'check_out' => $out === null || $visit->check_out_gps_status === null
                ? null
                : $this->place($visit->check_out_gps_status, $visit->check_out_gps_lat, $visit->check_out_gps_lng, $visit->check_out_gps_accuracy_meters, $visit),
            'radius' => $property?->geofence_radius_meters,
            'selfie_url' => $visit->check_in_selfie_file_id !== null ? "/admin/field-visits/{$visit->id}/selfie" : null,
            // Form defaults, in the hotel's time, for the datetime-local boxes.
            'in_local' => $in->format('Y-m-d\TH:i'),
            'out_local' => $out?->format('Y-m-d\TH:i'),
            'now_local' => CarbonImmutable::now($tz)->format('Y-m-d\TH:i'),
            'history' => Activity::query()
                ->where('subject_type', $visit->getMorphClass())
                ->where('subject_id', $visit->id)
                ->with('causer')
                ->oldest()
                ->orderBy('id')
                ->get()
                ->map(fn (Activity $a): array => [
                    'id' => $a->id,
                    'at' => $a->created_at?->setTimezone($tz)->format('M j, g:i A'),
                    'text' => $a->description,
                    'by' => $a->causer instanceof Person ? $a->causer->name : null,
                ])->all(),
            'can' => [
                'close' => $user->can('close', $visit),
                'fix' => $user->can('fixTimes', $visit),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function place(?string $status, mixed $lat, mixed $lng, ?int $accuracy, FieldVisit $visit): array
    {
        $has = $lat !== null && $lng !== null;
        $distance = $has && $visit->property ? Geofence::distanceToProperty($visit->property, (float) $lat, (float) $lng) : null;

        return [
            'gps_status' => $status,
            'accuracy' => $accuracy,
            'distance' => $distance === null ? null : (int) round($distance),
            'maps_url' => $has ? 'https://www.google.com/maps/search/?api=1&query='.((float) $lat).','.((float) $lng) : null,
        ];
    }
}
