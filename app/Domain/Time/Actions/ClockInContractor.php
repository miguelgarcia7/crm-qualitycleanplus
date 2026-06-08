<?php

namespace App\Domain\Time\Actions;

use App\Domain\Shared\Support\Geofence;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Events\TimeEntrySaved;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Support\StoreSelfie;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Opens a QR clock-in time entry for a contractor (Phase 07a, ADR-0017). Enforces
 * the property geofence (blocking — anti-fraud), requires a selfie, snapshots the
 * work order's rates, and leaves the entry open (no end / duration) until clock-out.
 *
 * @phpstan-type ClockData array{lat: float, lng: float, accuracy?: int|null, selfie: UploadedFile}
 */
class ClockInContractor
{
    /**
     * @param  ClockData  $data
     */
    public function handle(WorkOrder $workOrder, array $data): TimeEntry
    {
        $property = $workOrder->property;

        if (TimeEntry::query()->where('person_id', $workOrder->person_id)->whereNull('end_at_utc')->exists()) {
            throw ValidationException::withMessages([
                'work_order_id' => 'You are already clocked in. Please clock out first.',
            ]);
        }

        if (! Geofence::contains($property, $data['lat'], $data['lng'])) {
            $distance = Geofence::distanceToProperty($property, $data['lat'], $data['lng']);
            throw ValidationException::withMessages([
                'gps' => $distance === null
                    ? 'This property has no location configured yet — clock-in is unavailable.'
                    : 'You appear to be '.(int) round($distance)." meters from {$property->name}. Clock-in is only available at the property.",
            ]);
        }

        $now = CarbonImmutable::now('UTC');
        $period = $this->openPeriodForDate($workOrder, $now->setTimezone($property->timezone)->toDateString());

        $entry = TimeEntry::create([
            'person_id' => $workOrder->person_id,
            'work_order_id' => $workOrder->id,
            'property_id' => $workOrder->property_id,
            'payroll_period_id' => $period->id,
            'source' => TimeEntrySource::ClockEvent,
            'clock_method' => 'qr',
            'entry_type' => TimeEntryType::Work,
            'start_at_utc' => $now,
            'timezone' => $property->timezone,
            'pay_rate_snapshot' => $workOrder->pay_rate,
            'bill_rate_snapshot' => $workOrder->bill_rate,
            'ot_pay_rate_snapshot' => $workOrder->ot_pay_rate,
            'ot_bill_rate_snapshot' => $workOrder->ot_bill_rate,
            'clock_in_gps_lat' => $data['lat'],
            'clock_in_gps_lng' => $data['lng'],
            'clock_in_gps_accuracy_meters' => $data['accuracy'] ?? null,
        ]);

        $entry->update(['clock_in_selfie_file_id' => StoreSelfie::for($data['selfie'], $entry, $workOrder->person_id)->id]);

        TimeEntrySaved::dispatch($entry->property_id, $period->week_start->toDateString());

        return $entry;
    }

    private function openPeriodForDate(WorkOrder $workOrder, string $date): PayrollPeriod
    {
        $weekStart = CarbonImmutable::parse($date)->startOfWeek(CarbonImmutable::MONDAY)->toDateString();

        $period = PayrollPeriod::query()
            ->where('property_id', $workOrder->property_id)
            ->whereDate('week_start', $weekStart)
            ->first();

        if ($period === null || ! $period->status->isEditable()) {
            throw ValidationException::withMessages([
                'work_order_id' => 'This week is not open for clock-in yet. Please contact your recruiter.',
            ]);
        }

        return $period;
    }
}
