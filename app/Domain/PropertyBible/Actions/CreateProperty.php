<?php

namespace App\Domain\PropertyBible\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Concerns\NormalisesDirectHireThreshold;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Models\Property;

class CreateProperty
{
    use LogsPropertyActivity, NormalisesDirectHireThreshold;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Person $creator): Property
    {
        $property = new Property($this->normaliseDirectHireThreshold($data));
        $property->created_by = $creator?->id;
        $property->save();

        // Every property starts with the default holiday set; the Bible's
        // Holidays tab adjusts it from there.
        $property->holidays()->syncWithoutDetaching(
            Holiday::query()->whereIn('slug', Holiday::DEFAULT_ENABLED_SLUGS)->pluck('id')->all(),
        );

        $this->logProperty($property, 'created', "Created property \"{$property->name}\"");

        return $property;
    }
}
