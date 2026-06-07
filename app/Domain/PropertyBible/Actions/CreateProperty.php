<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Property;

class CreateProperty
{
    use LogsPropertyActivity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Person $creator): Property
    {
        $property = new Property($data);
        $property->created_by = $creator?->id;
        $property->save();

        $this->logProperty($property, 'created', "Created property \"{$property->name}\"");

        return $property;
    }
}
