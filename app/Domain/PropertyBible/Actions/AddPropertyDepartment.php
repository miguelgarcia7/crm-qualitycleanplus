<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Department;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyDepartment;

class AddPropertyDepartment
{
    use LogsPropertyActivity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Property $property, array $data): PropertyDepartment
    {
        $department = $property->departments()->create($data);
        $name = Department::findOrFail($department->department_id)->name;

        $this->logProperty($property, 'updated', "Added department \"{$name}\"");

        return $department;
    }
}
