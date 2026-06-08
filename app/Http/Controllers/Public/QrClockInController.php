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
 * qcpstaffing.com/clock-in/{property}. Phone + GPS + selfie are the credential.
 * Routes are throttled. Lookups and clock events re-verify the phone owns the
 * work order / open entry so one contractor cannot clock another in.
 */
class QrClockInController extends Controller
{
    public function show(Property $property): Response
    {
        return $this->page($property);
    }

    public function lookup(Request $request, Property $property): Response
    {
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

    public function clockIn(Request $request, Property $property, ClockInContractor $action): Response
    {
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
        ]);
    }

    public function clockOut(Request $request, Property $property, ClockOutContractor $action): Response
    {
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
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0'],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{lat: float, lng: float, accuracy: int|null, selfie: UploadedFile}
     */
    private function clockData(Request $request, array $validated): array
    {
        /** @var UploadedFile $selfie */
        $selfie = $request->file('selfie');

        return [
            'lat' => (float) $validated['lat'],
            'lng' => (float) $validated['lng'],
            'accuracy' => isset($validated['accuracy']) ? (int) $validated['accuracy'] : null,
            'selfie' => $selfie,
        ];
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
            'property' => ['id' => $property->id, 'name' => $property->name, 'city' => $property->city, 'state' => $property->state],
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
