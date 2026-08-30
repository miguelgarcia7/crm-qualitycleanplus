<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Concerns\NormalisesDirectHireThreshold;
use App\Domain\PropertyBible\Models\Property;

class UpdateProperty
{
    use LogsPropertyActivity, NormalisesDirectHireThreshold;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Property $property, array $data): Property
    {
        $property->update($this->normaliseDirectHireThreshold($data));

        $this->logProperty($property, 'updated', "Updated property \"{$property->name}\"");

        return $property;
    }
}
