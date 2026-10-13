<?php

namespace App\Domain\People\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

/**
 * Hands contractors from one recruiter to another: their primary recruiter
 * changes, and so does whose My Tasks their open hours problems sit in.
 * Work orders, rates and hotels stay as they are (moving hotels is a
 * Transfer). Optionally the old recruiter's properties go along: given to
 * the new recruiter, and taken off the old one.
 *
 * Each contractor's History records the change and who made it.
 */
class ReassignContractors
{
    /**
     * @param  list<int>  $contractorIds
     * @return int how many contractors moved
     */
    public function handle(?Person $from, Person $to, array $contractorIds, Person $by, bool $giveProperties = false, bool $removeProperties = false): int
    {
        return DB::transaction(function () use ($from, $to, $contractorIds, $by, $giveProperties, $removeProperties): int {
            $contractors = Person::query()
                ->whereIn('id', $contractorIds)
                ->where(fn ($q) => $from === null ? $q->whereNull('primary_recruiter_id') : $q->where('primary_recruiter_id', $from->id))
                ->get();

            foreach ($contractors as $contractor) {
                $contractor->update(['primary_recruiter_id' => $to->id]);

                activity()
                    ->performedOn($contractor)
                    ->causedBy($by)
                    ->event('recruiter_changed')
                    ->withProperties(['from' => $from?->id, 'to' => $to->id])
                    ->log('Recruiter changed from '.($from->name ?? 'none').' to '.$to->name);
            }

            // Their open hours problems follow them to the new recruiter.
            if ($from !== null && $contractors->isNotEmpty()) {
                WorkflowStep::query()
                    ->where('assigned_to', $from->id)
                    ->where('status', StepStatus::Pending)
                    ->whereHas('workflow', fn ($w) => $w
                        ->where('type', WorkflowType::HoursProblem->value)
                        ->whereIn('subject_id', $contractors->pluck('id')))
                    ->update(['assigned_to' => $to->id]);
            }

            if ($from !== null && ($giveProperties || $removeProperties)) {
                $assignments = $from->propertyAssignments()->where('role', PropertyAssignmentRole::Recruiter->value)->get();

                foreach ($assignments as $assignment) {
                    if ($giveProperties) {
                        $assignment->property->assignments()->firstOrCreate([
                            'person_id' => $to->id,
                            'role' => PropertyAssignmentRole::Recruiter->value,
                        ]);
                    }

                    if ($removeProperties) {
                        $assignment->delete();
                    }
                }
            }

            return $contractors->count();
        });
    }
}
