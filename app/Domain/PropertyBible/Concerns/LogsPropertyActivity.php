<?php

namespace App\Domain\PropertyBible\Concerns;

use App\Domain\PropertyBible\Models\Property;
use Illuminate\Support\Facades\Auth;

/**
 * Records Bible changes against the Property itself so the History tab is a
 * single activity stream per property (20-domain/property-bible.md §5).
 */
trait LogsPropertyActivity
{
    protected function logProperty(Property $property, string $event, string $description): void
    {
        activity('property_bible')
            ->performedOn($property)
            ->causedBy(Auth::user())
            ->event($event)
            ->log($description);
    }
}
