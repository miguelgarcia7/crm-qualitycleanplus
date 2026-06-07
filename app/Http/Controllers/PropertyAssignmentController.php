<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Actions\AssignPersonToProperty;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Http\Requests\Property\StorePropertyAssignmentRequest;
use Illuminate\Http\RedirectResponse;

class PropertyAssignmentController extends Controller
{
    use LogsPropertyActivity;

    public function store(StorePropertyAssignmentRequest $request, Property $property, AssignPersonToProperty $action): RedirectResponse
    {
        $action->handle($property, $request->validated());

        return back()->with('success', 'Assignment added.');
    }

    public function destroy(Property $property, PropertyAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->property_id === $property->id, 404);
        $this->authorize('manageAssignments', $property);

        $assignment->delete();

        $this->logProperty($property, 'updated', 'Removed an assignment');

        return back()->with('success', 'Assignment removed.');
    }
}
