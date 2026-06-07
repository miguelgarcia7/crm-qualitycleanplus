<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;

class AssignPersonToProperty
{
    use LogsPropertyActivity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Property $property, array $data): PropertyAssignment
    {
        $assignment = $property->assignments()->create($data);
        $name = Person::findOrFail($assignment->person_id)->name;

        $this->logProperty($property, 'updated', "Assigned {$name} ({$assignment->role->label()})");

        return $assignment;
    }
}
