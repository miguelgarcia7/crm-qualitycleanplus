<?php

namespace App\Domain\FieldVisits\Actions;

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use Carbon\CarbonImmutable;

/**
 * Super admin corrects a visit's check-in or check-out time (field_visits.
 * manual_edit). Always with a reason, kept with the old and new times on the
 * visit's History. Setting a check-out on an open visit closes it.
 */
class FixVisitTimes
{
    public function handle(FieldVisit $visit, CarbonImmutable $in, ?CarbonImmutable $out, string $reason, Person $by): FieldVisit
    {
        $tz = ($visit->property->timezone ?? null) ?: config('app.company_timezone');
        $fmt = fn (?CarbonImmutable $at): string => $at === null ? 'still open' : $at->setTimezone($tz)->format('M j, g:i A');

        $before = 'in '.$fmt($visit->check_in_at).', out '.$fmt($visit->check_out_at);
        $after = 'in '.$fmt($in).', out '.$fmt($out ?? $visit->check_out_at);

        $visit->update(array_filter([
            'check_in_at' => $in,
            'check_out_at' => $out,
            'status' => $out !== null ? FieldVisitStatus::Closed : null,
        ], fn ($v) => $v !== null));

        activity()->performedOn($visit)->causedBy($by)->event('times_fixed')
            ->log("Times fixed from {$before} to {$after}. Reason: {$reason}");

        return $visit;
    }
}
