<?php

namespace App\Http\Controllers\Public;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\ClockInContractor;
use App\Domain\Time\Actions\ClockOutContractor;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, unauthenticated QR clock-in for contractors (Phase 07a, ADR-0017):
 * qcpstaffing.com/clock-in/{token} where the token is the property's minted
 * qr_token. Phone + GPS + selfie are the credential. Routes are throttled.
 * Unknown token and disabled property 404 identically (no URL probing).
 * Lookups and clock events re-verify the phone owns the work order / open
 * entry so one contractor cannot clock another in. GPS never hard-requires:
 * GpsPolicy flags degraded readings instead of locking the worker out.
 */
class QrClockInController extends Controller
{
    public function show(string $token): Response
    {
        return $this->page($this->property($token));
    }

    public function lookup(Request $request, string $token): Response
    {
        $property = $this->property($token);
        $validated = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $normalized = $this->normalizePhone($validated['phone']);

        $person = $normalized === null ? null
            : Person::query()->where('normalized_phone', $normalized)->first();

        if ($person === null) {
            return $this->page($property, ['phone' => $validated['phone'], 'error' => 'Phone number not recognized. Please contact your recruiter or HR.']);
        }

        $workOrders = WorkOrder::query()
            ->where('person_id', $person->id)->where('property_id', $property->id)
            ->where('status', WorkOrderStatus::Active->value)
            ->with('position:id,name')->get();

        $open = TimeEntry::query()->where('person_id', $person->id)->whereNull('end_at_utc')
            ->with('property:id,name', 'workOrder.position:id,name')->first();

        if ($workOrders->isEmpty() && $open === null) {
            return $this->page($property, ['phone' => $validated['phone'], 'error' => 'No active work order at this property. Please contact your recruiter.']);
        }

        return $this->page($property, [
            'phone' => $validated['phone'],
            'contractor' => $person->name,
            'work_orders' => $workOrders->map(fn (WorkOrder $w): array => ['id' => $w->id, 'position' => $w->position?->name])->all(),
            'open_entry' => $open === null ? null : [
                'id' => $open->id,
                'property' => $open->property?->name,
                'position' => $open->workOrder?->position?->name,
                'since' => $open->start_at_utc?->toIso8601String(),
                'same_property' => $open->property_id === $property->id,
            ],
        ]);
    }

    public function clockIn(Request $request, string $token, ClockInContractor $action): Response
    {
        $property = $this->property($token);
        $validated = $this->validateClock($request, ['work_order_id' => ['required', 'integer']]);

        $workOrder = WorkOrder::query()->where('property_id', $property->id)
            ->where('status', WorkOrderStatus::Active->value)
            ->find($validated['work_order_id']);

        if ($workOrder === null) {
            $this->reject('work_order_id', 'That work order is not available at this property.');
        }

        $this->assertPhoneOwns($workOrder->person, $validated['phone']);

        $entry = $action->handle($workOrder, $this->clockData($request, $validated));

        return $this->page($property, result: [
            'action' => 'in',
            'position' => $workOrder->position?->name,
            'at' => $entry->start_at_utc?->setTimezone($property->timezone)->format('g:i A'),
            'flagged' => $entry->clock_in_gps_flag_reason !== null,
        ]);
    }

    public function clockOut(Request $request, string $token, ClockOutContractor $action): Response
    {
        $property = $this->property($token);
        $validated = $this->validateClock($request, ['time_entry_id' => ['required', 'integer']]);

        $entry = TimeEntry::query()->where('property_id', $property->id)->whereNull('end_at_utc')
            ->find($validated['time_entry_id']);

        if ($entry === null) {
            $this->reject('time_entry_id', 'That clock-in could not be found.');
        }

        $this->assertPhoneOwns($entry->person, $validated['phone']);

        $closed = $action->handle($entry, $this->clockData($request, $validated));

        return $this->page($property, result: [
            'action' => 'out',
            'duration' => $this->humanDuration((int) $closed->duration_minutes),
            'at' => $closed->end_at_utc?->setTimezone($property->timezone)->format('g:i A'),
            'flagged' => $closed->clock_out_gps_flag_reason !== null,
        ]);
    }

    /**
     * @param  array<string, array<int, string>>  $extra
     * @return array<string, mixed>
     */
    private function validateClock(Request $request, array $extra): array
    {
        return $request->validate($extra + [
            'phone' => ['required', 'string', 'max:32'],
            // GPS is flag-not-block (GpsPolicy): a phone with no fix still punches.
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0'],
            'gps_failure_reason' => ['nullable', 'string', 'in:permission_denied,no_fix'],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{lat: float|null, lng: float|null, accuracy: int|null, gps_failure_reason: string|null, selfie: UploadedFile}
     */
    private function clockData(Request $request, array $validated): array
    {
        /** @var UploadedFile $selfie */
        $selfie = $request->file('selfie');

        return [
            'lat' => isset($validated['lat']) ? (float) $validated['lat'] : null,
            'lng' => isset($validated['lng']) ? (float) $validated['lng'] : null,
            'accuracy' => isset($validated['accuracy']) ? (int) $validated['accuracy'] : null,
            'gps_failure_reason' => $validated['gps_failure_reason'] ?? null,
            'selfie' => $selfie,
        ];
    }

    /** Unknown token and disabled property are indistinguishable — both 404. */
    private function property(string $token): Property
    {
        return Property::query()
            ->where('qr_token', $token)
            ->where('qr_clock_enabled', true)
            ->firstOr(fn () => abort(404));
    }

    private function assertPhoneOwns(?Person $person, string $phone): void
    {
        if ($person === null || $person->normalized_phone !== $this->normalizePhone($phone)) {
            $this->reject('phone', 'That phone number does not match this work order.');
        }
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /**
     * @param  array<string, mixed>|null  $lookup
     * @param  array<string, mixed>|null  $result
     */
    private function page(Property $property, ?array $lookup = null, ?array $result = null): Response
    {
        return Inertia::render('public/clock-in/index', array_filter([
            'property' => ['token' => $property->qr_token, 'name' => $property->name, 'city' => $property->city, 'state' => $property->state],
            'lookup' => $lookup,
            'result' => $result,
        ], fn ($v) => $v !== null));
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return ($digits === null || $digits === '') ? null : substr($digits, 0, 15);
    }

    private function humanDuration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}
