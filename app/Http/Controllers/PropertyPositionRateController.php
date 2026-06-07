<?php

namespace App\Http\Controllers;

use App\Domain\PropertyBible\Actions\AddPropertyPositionRate;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use App\Http\Requests\Property\StorePropertyPositionRateRequest;
use Illuminate\Http\RedirectResponse;

class PropertyPositionRateController extends Controller
{
    use LogsPropertyActivity;

    public function store(StorePropertyPositionRateRequest $request, Property $property, AddPropertyPositionRate $action): RedirectResponse
    {
        $action->handle($property, $request->validated(), $request->user());

        return back()->with('success', 'Rate added.');
    }

    public function destroy(Property $property, PropertyPositionRate $rate): RedirectResponse
    {
        abort_unless($rate->property_id === $property->id, 404);
        $this->authorize('editRates', $property);

        $rate->delete();

        $this->logProperty($property, 'updated', 'Removed a rate');

        return back()->with('success', 'Rate removed.');
    }
}
