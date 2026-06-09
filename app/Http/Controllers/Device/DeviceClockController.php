<?php

namespace App\Http\Controllers\Device;

use App\Domain\Devices\Actions\ActivateDevice;
use App\Domain\Devices\Models\Device;
use App\Domain\People\Models\Person;
use App\Domain\Time\Actions\ClockInContractor;
use App\Domain\Time\Actions\ClockOutContractor;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Tablet kiosk JSON API (Phase 07c, ADR-0017). `activate` is public; the rest are
 * authenticated by the device's Sanctum token (`auth:device`). The device pins clock
 * events to its property; the phone identifies the contractor per action. No GPS —
 * the paired device is the location proof (no geofence).
 */
class DeviceClockController extends Controller
{
    public function activate(Request $request, ActivateDevice $action): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $result = $action->handle(trim($validated['code']));
        $device = $result['device']->load('property:id,name');

        return response()->json([
            'token' => $result['token'],
            'property' => ['id' => $device->property?->id, 'name' => $device->property?->name],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $device->markSeen($request->string('app_version')->toString() ?: null);
        $device->loadMissing('property:id,name');

        return response()->json([
            'device' => ['id' => $device->id, 'name' => $device->name],
            'property' => ['id' => $device->property?->id, 'name' => $device->property?->name],
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $validated = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $normalized = $this->normalizePhone($validated['phone']);

        $person = $normalized === null ? null : Person::query()->where('normalized_phone', $normalized)->first();
        if ($person === null) {
            return response()->json(['error' => 'Phone number not recognized.'], 422);
        }

        $workOrders = WorkOrder::query()
            ->where('person_id', $person->id)->where('property_id', $device->property_id)
            ->where('status', WorkOrderStatus::Active->value)
            ->with('position:id,name')->get();

        $open = TimeEntry::query()->where('person_id', $person->id)->whereNull('end_at_utc')
            ->with('workOrder.position:id,name')->first();

        if ($workOrders->isEmpty() && $open === null) {
            return response()->json(['error' => 'No active work order at this property.'], 422);
        }

        return response()->json([
            'contractor' => $person->name,
            'work_orders' => $workOrders->map(fn (WorkOrder $w): array => ['id' => $w->id, 'position' => $w->position?->name]),
            'open_entry' => $open === null ? null : [
                'id' => $open->id,
                'position' => $open->workOrder?->position?->name,
                'same_property' => $open->property_id === $device->property_id,
            ],
        ]);
    }

    public function clockIn(Request $request, ClockInContractor $action): JsonResponse
    {
        $device = $this->device($request);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'work_order_id' => ['required', 'integer'],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);

        $workOrder = WorkOrder::query()->where('property_id', $device->property_id)
            ->where('status', WorkOrderStatus::Active->value)->find($validated['work_order_id']);
        if ($workOrder === null) {
            throw ValidationException::withMessages(['work_order_id' => 'That work order is not available at this property.']);
        }
        $this->assertPhoneOwns($workOrder->person, $validated['phone']);

        $entry = $action->handle($workOrder, ['selfie' => $request->file('selfie')], clockMethod: 'tablet', enforceGeofence: false);

        return response()->json(['ok' => true, 'action' => 'in', 'at' => $entry->start_at_utc?->setTimezone($device->property->timezone)->format('g:i A')]);
    }

    public function clockOut(Request $request, ClockOutContractor $action): JsonResponse
    {
        $device = $this->device($request);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'time_entry_id' => ['required', 'integer'],
            'selfie' => ['nullable', 'image', 'max:5120'],
        ]);

        $entry = TimeEntry::query()->where('property_id', $device->property_id)->whereNull('end_at_utc')->find($validated['time_entry_id']);
        if ($entry === null) {
            throw ValidationException::withMessages(['time_entry_id' => 'That clock-in could not be found.']);
        }
        $this->assertPhoneOwns($entry->person, $validated['phone']);

        $closed = $action->handle($entry, ['selfie' => $request->file('selfie')]);

        return response()->json(['ok' => true, 'action' => 'out', 'duration_minutes' => $closed->duration_minutes]);
    }

    private function device(Request $request): Device
    {
        $device = $request->user();
        abort_unless($device instanceof Device, 401);

        return $device;
    }

    private function assertPhoneOwns(?Person $person, string $phone): void
    {
        if ($person === null || $person->normalized_phone !== $this->normalizePhone($phone)) {
            throw ValidationException::withMessages(['phone' => 'That phone number does not match this work order.']);
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return ($digits === null || $digits === '') ? null : substr($digits, 0, 15);
    }
}
