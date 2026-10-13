<?php

namespace App\Domain\People\Actions;

use App\Domain\People\Models\Person;
use App\Domain\People\Support\StaffRoles;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets which office roles a staff member holds (StaffRoles). Taking away
 * Recruiter while they still have contractors or properties is refused, so
 * nobody is left without a recruiter: move those first. The change is logged
 * on the person's History.
 */
class UpdatePersonRoles
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(Person $person, array $roles, Person $by): Person
    {
        $roles = array_values(array_intersect(array_keys(StaffRoles::LABELS), $roles));
        $before = array_values(array_intersect(array_keys(StaffRoles::LABELS), $person->getRoleNames()->all()));

        if (in_array('recruiter', $before, true) && ! in_array('recruiter', $roles, true)) {
            $contractors = Person::query()->where('primary_recruiter_id', $person->id)->count();
            $properties = $person->propertyAssignments()->where('role', PropertyAssignmentRole::Recruiter->value)->count();

            if ($contractors > 0 || $properties > 0) {
                throw ValidationException::withMessages([
                    'roles' => "{$person->name} still has {$contractors} ".($contractors === 1 ? 'contractor' : 'contractors')
                        ." and {$properties} ".($properties === 1 ? 'property' : 'properties').'. Move them to another recruiter first.',
                ]);
            }
        }

        DB::transaction(function () use ($person, $roles, $before, $by): void {
            $person->syncRoles($roles);

            $label = fn (array $list): string => $list === [] ? 'none' : implode(', ', array_map(fn (string $r): string => StaffRoles::LABELS[$r], $list));

            activity()
                ->performedOn($person)
                ->causedBy($by)
                ->event('roles_changed')
                ->withProperties(['from' => $before, 'to' => $roles])
                ->log("Roles changed from {$label($before)} to {$label($roles)}");
        });

        return $person->refresh();
    }
}
