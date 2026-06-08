<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\ReturnEquipment;
use App\Domain\Inventory\Enums\EquipmentAssignmentStatus;
use App\Domain\Inventory\Models\EquipmentAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Equipment assignments (ADR-0012): view who has what, and mark items returned
 * or lost outside the termination flow.
 */
class EquipmentAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $assignments = EquipmentAssignment::query()
            ->with(['assignedTo:id,name', 'itemVariant.item:id,name'])
            ->where('status', EquipmentAssignmentStatus::Assigned)
            ->latest('id')
            ->get();

        return Inertia::render('admin/inventory/equipment', [
            'assignments' => $assignments->map(fn (EquipmentAssignment $a): array => [
                'id' => $a->id,
                'person' => $a->assignedTo->name,
                'item' => $a->itemVariant->item->name.' — '.$a->itemVariant->label(),
                'quantity' => $a->quantity,
                'assigned_at' => $a->assigned_at?->toDateString(),
            ]),
            'can' => [
                'return' => $request->user()->can('inventory.equipment.return'),
            ],
        ]);
    }

    public function return(Request $request, EquipmentAssignment $assignment, ReturnEquipment $action): RedirectResponse
    {
        $validated = $request->validate([
            'returned' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle($assignment, (bool) $validated['returned'], $validated['notes'] ?? null, $request->user());

        return back()->with('success', 'Equipment updated.');
    }
}
