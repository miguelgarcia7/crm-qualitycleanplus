<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Models\Device;
use Illuminate\Validation\ValidationException;

/**
 * Pairs a tablet to its property (Phase 07c): matches the activation code, mints a
 * Sanctum device token, marks the device activated, and regenerates the code so it
 * can't be reused. Returns the plain-text token + device.
 */
class ActivateDevice
{
    /**
     * @return array{device: Device, token: string}
     */
    public function handle(string $code): array
    {
        $device = Device::query()->where('activation_code', $code)->first();

        if ($device === null) {
            throw ValidationException::withMessages(['code' => 'That activation code is not valid.']);
        }

        $device->forceFill(['is_activated' => true, 'last_seen_at' => now()])->save();
        $token = $device->createToken('device')->plainTextToken;
        $device->regenerateCode();

        return ['device' => $device, 'token' => $token];
    }
}
