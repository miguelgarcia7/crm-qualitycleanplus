<?php

namespace App\Domain\FieldVisits\Actions;

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use Carbon\CarbonImmutable;

/**
 * The office closes a visit the recruiter forgot to check out of, at the time
 * they left. It counts as closed late and records who closed it; the note goes
 * on the visit's History (the visit's own notes are the recruiter's).
 */
class CloseVisitForRecruiter
{
    public function handle(FieldVisit $visit, CarbonImmutable $leftAt, ?string $note, Person $by): FieldVisit
    {
        $visit->update([
            'status' => FieldVisitStatus::Closed,
            'check_out_at' => $leftAt,
            'was_late_close' => true,
            'closed_by_person_id' => $by->id,
        ]);

        $tz = $visit->property->timezone ?? null;
        activity()->performedOn($visit)->causedBy($by)->event('closed_by_office')
            ->log(trim('Closed by the office: left at '.$leftAt->setTimezone($tz ?: config('app.company_timezone'))->format('M j, g:i A').'. '.($note ?? '')));

        return $visit;
    }
}
