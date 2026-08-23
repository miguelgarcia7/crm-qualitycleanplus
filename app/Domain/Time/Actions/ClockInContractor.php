<?php

namespace App\Domain\Time\Actions;

use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Events\TimeEntrySaved;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Support\GpsPolicy;
use App\Domain\Time\Support\StoreSelfie;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\PunchFlagged;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Opens a QR clock-in time entry for a contractor (Phase 07a, ADR-0017).
 * GPS runs through GpsPolicy (flag-and-notify): only a trusted fix outside
 * the fence blocks; degraded GPS clocks in flagged and the property's
 * recruiters are notified. Requires a selfie, snapshots the work order's
 * rates, and leaves the entry open (no end / duration) until clock-out.
 *
 * @phpstan-type ClockData array{lat?: float|null, lng?: float|null, accuracy?: int|null, gps_failure_reason?: string|null, selfie: UploadedFile}
 */
class ClockInContractor
{
    /**
     * @param  ClockData  $data
     */
    public function handle(WorkOrder $workOrder, array $data, string $clockMethod = 'qr', bool $enforceGeofence = true): TimeEntry
    {
        $property = $workOrder->property;
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        if (TimeEntry::query()->where('person_id', $workOrder->person_id)->whereNull('end_at_utc')->exists()) {
            throw ValidationException::withMessages([
                'work_order_id' => 'You are already clocked in. Please clock out first.',
            ]);
        }

        $flagReason = null;
        if ($enforceGeofence) {
            $gps = GpsPolicy::evaluate($property, $lat, $lng, $data['accuracy'] ?? null, $data['gps_failure_reason'] ?? null);

            if ($gps['outcome'] === GpsPolicy::OUTCOME_BLOCKED) {
                throw ValidationException::withMessages([
                    'gps' => 'You appear to be '.(int) round((float) $gps['distance'])." meters from {$property->name}. Clock-in is only available at the property.",
                ]);
            }

            $flagReason = $gps['flag_reason'];
        }

        $now = CarbonImmutable::now('UTC');
        $period = $this->openPeriodForDate($workOrder, $now->setTimezone($property->timezone)->toDateString());

        $entry = TimeEntry::create([
            'person_id' => $workOrder->person_id,
            'work_order_id' => $workOrder->id,
            'property_id' => $workOrder->property_id,
            'payroll_period_id' => $period->id,
            'source' => TimeEntrySource::ClockEvent,
            'clock_method' => $clockMethod,
            'entry_type' => TimeEntryType::Work,
            'start_at_utc' => $now,
            'timezone' => $property->timezone,
            'pay_rate_snapshot' => $workOrder->pay_rate,
            'bill_rate_snapshot' => $workOrder->bill_rate,
            'ot_pay_rate_snapshot' => $workOrder->ot_pay_rate,
            'ot_bill_rate_snapshot' => $workOrder->ot_bill_rate,
            'clock_in_gps_lat' => $lat,
            'clock_in_gps_lng' => $lng,
            'clock_in_gps_accuracy_meters' => $data['accuracy'] ?? null,
            'clock_in_gps_flag_reason' => $flagReason,
        ]);

        $entry->update(['clock_in_selfie_file_id' => StoreSelfie::for($data['selfie'], $entry, $workOrder->person_id)->id]);

        if ($flagReason !== null) {
            self::notifyRecruiters($entry, 'in', $flagReason);
        }

        TimeEntrySaved::dispatch($entry->property_id, $period->week_start->toDateString());

        return $entry;
    }

    /** Flag review goes to the property's recruiters (the people who manage the contractor). */
    public static function notifyRecruiters(TimeEntry $entry, string $direction, string $reason): void
    {
        $recruiters = $entry->property?->assignments()
            ->where('role', PropertyAssignmentRole::Recruiter->value)
            ->with('person')
            ->get()
            ->pluck('person')
            ->filter() ?? collect();

        Notification::send($recruiters, new PunchFlagged($entry, $direction, $reason));
    }

    private function openPeriodForDate(WorkOrder $workOrder, string $date): PayrollPeriod
    {
        $weekStart = $workOrder->property->weekStartFor($date)->toDateString();

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
