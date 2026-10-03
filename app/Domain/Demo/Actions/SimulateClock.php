<?php

namespace App\Domain\Demo\Actions;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Demo\Concerns\TravelsInTime;
use App\Domain\Demo\DemoRoster;
use App\Domain\Demo\Support\PlaceholderSelfie;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\ClockInContractor;
use App\Domain\Time\Actions\ClockOutContractor;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Plays the demo roster's planned punches forward to $until through the real
 * clock actions, so Acme behaves like contractors on the QR page and the lobby
 * tablet: entries, selfies, weekly summaries, overtime, live broadcasts.
 *
 * Each punch is stamped at its planned time rather than the cron tick that
 * found it due, so a missed run, or the seeder replaying six weeks of history,
 * records the same punches. Idempotent: a day's progress is read back from its
 * entries, and only punches whose time has come are added.
 */
class SimulateClock
{
    use TravelsInTime;

    public function __construct(
        private ClockInContractor $clockIn,
        private ClockOutContractor $clockOut,
    ) {}

    /** @return int punches recorded */
    public function handle(Property $property, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $tz = $property->timezone;
        $workOrders = $property->workOrders()
            ->where('status', WorkOrderStatus::Active)
            ->with('person')
            ->get();

        $punches = 0;
        for ($day = $from->setTimezone($tz)->startOfDay(); $day <= $until; $day = $day->addDay()) {
            foreach ($workOrders as $workOrder) {
                $contractor = DemoRoster::contractor((string) $workOrder->person?->email);
                if ($contractor === null || $workOrder->start_date->toDateString() > $day->toDateString()) {
                    continue;
                }

                $shifts = DemoRoster::shiftsFor($contractor['shift'], $workOrder->person_id, $day, $tz);
                if ($shifts === []) {
                    continue;
                }

                $this->ensurePeriod($property, $day);
                $punches += $this->playDay($workOrder, $contractor['method'], $shifts, $day, $until);
            }
        }

        return $punches;
    }

    /**
     * Records the day's punches that are due by $until, one at a time.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $shifts
     */
    private function playDay(WorkOrder $workOrder, string $method, array $shifts, CarbonImmutable $day, CarbonImmutable $until): int
    {
        $punches = 0;

        while (true) {
            $entries = TimeEntry::query()
                ->where('work_order_id', $workOrder->id)
                ->whereBetween('start_at_utc', [$day->utc(), $day->endOfDay()->utc()])
                ->orderBy('start_at_utc')
                ->get();
            $last = $entries->last();

            if ($last !== null && $last->end_at_utc === null) {
                $out = ($shifts[$entries->count() - 1] ?? $shifts[array_key_last($shifts)])[1];
                if ($out > $until) {
                    return $punches;
                }
                $this->at($out, fn () => $this->punchOut($last, $method));
            } else {
                $in = $shifts[$entries->count()][0] ?? null;
                if ($in === null || $in > $until) {
                    return $punches;
                }

                try {
                    $this->at($in, fn () => $this->punchIn($workOrder, $method));
                } catch (ValidationException) {
                    // Someone else's punch is open, or the week is already
                    // locked for approval — leave this contractor's day alone.
                    return $punches;
                }
            }

            $punches++;
        }
    }

    private function punchIn(WorkOrder $workOrder, string $method): void
    {
        $selfie = PlaceholderSelfie::for($workOrder->person_id);

        try {
            $this->clockIn->handle(
                $workOrder,
                [...$this->gps($workOrder, $method), 'selfie' => $selfie],
                $method,
                enforceGeofence: $method === 'qr',
            );
        } finally {
            @unlink($selfie->getPathname());
        }
    }

    /** The QR page asks for a selfie on the way out too; the tablet doesn't. */
    private function punchOut(TimeEntry $entry, string $method): void
    {
        $selfie = $method === 'qr' ? PlaceholderSelfie::for($entry->person_id) : null;

        try {
            $this->clockOut->handle(
                $entry,
                [...$this->gps($entry->workOrder, $method), 'selfie' => $selfie],
                evaluateGps: $method === 'qr',
            );
        } finally {
            if ($selfie !== null) {
                @unlink($selfie->getPathname());
            }
        }
    }

    /**
     * A phone fix a few meters inside the property's geofence for QR punches;
     * the lobby tablet sends no location.
     *
     * @return array{lat?: float, lng?: float, accuracy?: int}
     */
    private function gps(?WorkOrder $workOrder, string $method): array
    {
        $property = $workOrder?->property;
        if ($method !== 'qr' || $property?->latitude === null || $property->longitude === null) {
            return [];
        }

        return [
            'lat' => (float) $property->latitude + random_int(-15, 15) / 100000,
            'lng' => (float) $property->longitude + random_int(-15, 15) / 100000,
            'accuracy' => random_int(6, 18),
        ];
    }

    /**
     * The week's payroll period + draft timesheet, as payroll:ensure-periods
     * makes them — that command only reaches back one week, and the replayed
     * history goes back six.
     */
    private function ensurePeriod(Property $property, CarbonImmutable $day): void
    {
        $weekStart = $property->weekStartFor($day);

        $period = $property->payrollPeriods()->whereDate('week_start', $weekStart->toDateString())->first()
            ?? $property->payrollPeriods()->create([
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->addDays(6)->toDateString(),
                'status' => PayrollPeriodStatus::Open,
            ]);

        $period->timesheet()->firstOrCreate(
            [],
            ['property_id' => $property->id, 'source' => 'clock_in', 'status' => TimesheetStatus::Draft],
        );
    }
}
