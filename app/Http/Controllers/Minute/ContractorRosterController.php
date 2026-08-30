<?php

namespace App\Http\Controllers\Minute;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The contractors currently placed at a property manager's properties, with
 * progress toward direct-hire eligibility.
 *
 * The threshold is the commercial term that stops a property hiring a QCP
 * contractor out from under us before the placement has paid for itself, so the
 * PM sees exactly where each contractor stands rather than having to ask.
 * Read-only — pay rates and anything else commercial stay out of it.
 */
class ContractorRosterController extends Controller
{
    public function index(DirectHireProgress $progress): Response
    {
        $user = Auth::user();

        $workOrders = WorkOrder::query()
            ->where('status', WorkOrderStatus::Active->value)
            ->when(
                $user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES),
                fn ($q) => $q->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all()),
            )
            ->with(['person:id,name', 'property:id,name', 'position:id,name'])
            ->get();

        $byWorkOrder = $progress->forMany($workOrders);

        return Inertia::render('minute/contractors/index', [
            'contractors' => $workOrders
                ->sortBy(fn (WorkOrder $wo): string => (string) $wo->person?->name)
                ->map(fn (WorkOrder $wo): array => [
                    'id' => $wo->id,
                    'name' => $wo->person?->name,
                    'position' => $wo->position?->name,
                    'property' => $wo->property?->name,
                    'started_on' => $wo->start_date->toDateString(),
                    'direct_hire' => $byWorkOrder[$wo->id] ?? null,
                ])
                ->values(),
        ]);
    }
}
