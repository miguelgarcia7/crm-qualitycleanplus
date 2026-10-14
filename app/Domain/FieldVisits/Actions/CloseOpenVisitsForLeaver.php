<?php

namespace App\Domain\FieldVisits\Actions;

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;

/**
 * Someone is leaving (termination): any visit they left open is closed now,
 * marked closed late, so it doesn't sit "open" forever on the Field Visits
 * list (ADR-0018). Each one says why on its History.
 */
class CloseOpenVisitsForLeaver
{
    public function handle(Person $person, ?Person $by): int
    {
        $visits = FieldVisit::query()->where('person_id', $person->id)->open()->get();

        foreach ($visits as $visit) {
            $visit->update([
                'status' => FieldVisitStatus::Closed,
                'check_out_at' => now(),
                'was_late_close' => true,
                'closed_by_person_id' => $by?->id,
            ]);

            activity()->performedOn($visit)->causedBy($by)->event('closed_on_termination')
                ->log("Closed because {$person->name} was terminated");
        }

        return $visits->count();
    }
}
