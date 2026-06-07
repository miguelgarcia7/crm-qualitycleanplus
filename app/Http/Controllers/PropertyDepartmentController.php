<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Actions\AddPropertyDepartment;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyDepartment;
use App\Http\Requests\Property\StorePropertyDepartmentRequest;
use App\Http\Requests\Property\UpdatePropertyDepartmentRequest;
use Illuminate\Http\RedirectResponse;

class PropertyDepartmentController extends Controller
{
    use LogsPropertyActivity;

    public function store(StorePropertyDepartmentRequest $request, Property $property, AddPropertyDepartment $action): RedirectResponse
    {
        $action->handle($property, $request->validated());

        return back()->with('success', 'Department added.');
    }

    public function update(UpdatePropertyDepartmentRequest $request, Property $property, PropertyDepartment $department): RedirectResponse
    {
        abort_unless($department->property_id === $property->id, 404);

        $department->update($request->validated());

        $this->logProperty($property, 'updated', 'Updated a department');

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Property $property, PropertyDepartment $department): RedirectResponse
    {
        abort_unless($department->property_id === $property->id, 404);
        $this->authorize('editDepartments', $property);

        $department->delete();

        $this->logProperty($property, 'updated', 'Removed a department');

        return back()->with('success', 'Department removed.');
    }
}
