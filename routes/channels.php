<?php

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use Illuminate\Support\Facades\Broadcast;

// Live timesheet grid (Phase 03): recruiters/PMs assigned to the property, plus
// global roles, may subscribe to its private channel.
Broadcast::channel('property.{propertyId}', function (Person $user, int $propertyId): bool {
    $property = Property::find($propertyId);

    if ($property === null) {
        return false;
    }

    if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
        return true;
    }

    return $user->isAssignedTo($property);
});
