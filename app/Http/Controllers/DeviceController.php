<?php

namespace App\Http\Controllers;

use App\Domain\Devices\Models\Device;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office management of front-desk tablets (Phase 07c). Create a device for a
 * property (issues an activation code to type on the tablet), regenerate the code,
 * or revoke (deletes the device + its tokens). Gated `devices.manage`.
 */
class DeviceController extends Controller
{
    public function index(): Response
    {
        $devices = Device::query()->with(['property:id,name', 'createdBy:id,name'])->latest('id')->get()
            ->map(fn (Device $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'property' => $d->property?->name,
                'activation_code' => $d->is_activated ? null : $d->activation_code,
                'is_activated' => $d->is_activated,
                'last_seen_at' => $d->last_seen_at?->diffForHumans(),
                'app_version' => $d->app_version,
            ]);

        return Inertia::render('admin/devices/index', [
            'devices' => $devices,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'property_id' => ['required', 'integer', 'exists:properties,id'],
        ]);

        $device = Device::create([
            'name' => $validated['name'],
            'property_id' => $validated['property_id'],
            'activation_code' => Device::newCode(),
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', "Device created — activation code: {$device->activation_code}");
    }

    public function regenerate(Device $device): RedirectResponse
    {
        $device->tokens()->delete();
        $device->forceFill(['is_activated' => false])->save();
        $device->regenerateCode();

        return back()->with('success', "New activation code: {$device->activation_code}");
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->tokens()->delete();
        $device->delete();

        return back()->with('success', 'Device revoked.');
    }
}
