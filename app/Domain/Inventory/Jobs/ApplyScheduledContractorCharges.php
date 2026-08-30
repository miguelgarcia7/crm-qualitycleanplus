<?php

namespace App\Domain\Inventory\Jobs;

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Inventory\Actions\AllocateChargeScheduleEntries;
use App\Domain\Inventory\Enums\ChargeEntryStatus;
use App\Domain\Inventory\Enums\ChargeReason;
use App\Domain\Inventory\Enums\ChargeScheduleStatus;
use App\Domain\Inventory\Models\ContractorChargeSchedule;
use App\Domain\Inventory\Models\ContractorChargeScheduleEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Turns each scheduled charge entry whose payroll period is currently open into a
 * non-billable payroll deduction (ADR-0014). Runs at the start of each period
 * (scheduled daily) and is idempotent — applied entries are skipped.
 */
class ApplyScheduledContractorCharges implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Top up schedules too long for the open-period window (a $250 hiring
        // fee at $20 needs 13 periods; only a few are ever open) before
        // applying anything.
        $allocate = app(AllocateChargeScheduleEntries::class);

        ContractorChargeSchedule::query()
            ->where('status', ChargeScheduleStatus::Active)
            ->with('person')
            ->each(fn (ContractorChargeSchedule $schedule) => $allocate->handle($schedule));

        $entries = ContractorChargeScheduleEntry::query()
            ->where('status', ChargeEntryStatus::Scheduled)
            ->whereHas('payrollPeriod', fn (Builder $q) => $q->where('status', 'open'))
            ->with(['schedule', 'payrollPeriod'])
            ->get();

        foreach ($entries as $entry) {
            DB::transaction(function () use ($entry): void {
                $schedule = $entry->schedule;
                $period = $entry->payrollPeriod;

                $adjustment = TimeEntryAdjustment::create([
                    'person_id' => $schedule->person_id,
                    'property_id' => $period->property_id,
                    'payroll_period_id' => $period->id,
                    // A hiring fee has no source request — it is QCP's own
                    // charge, not something the contractor requested.
                    'source_type' => $schedule->reason === ChargeReason::HiringFee
                        ? AdjustmentSourceType::Manual
                        : AdjustmentSourceType::SupplyRequest,
                    'source_id' => $schedule->source_request_id,
                    'value' => $entry->amount,
                    'type' => AdjustmentType::Deduction,
                    'is_billable' => false,
                    'notes' => $schedule->reason->label().' (payment '.$entry->payment_index.' of '.$schedule->num_payments.')',
                ]);

                $entry->update([
                    'status' => ChargeEntryStatus::Applied,
                    'applied_adjustment_id' => $adjustment->id,
                ]);

                // Complete only once the whole amount has actually been taken.
                // Checking "no scheduled entries left" was safe when every
                // payment was allocated up front, but a schedule longer than
                // the open-period window is allocated in instalments — that
                // test would retire it early and strand the remainder, since
                // the allocator only tops up ACTIVE schedules.
                $collected = (int) $schedule->entries()
                    ->where('status', ChargeEntryStatus::Applied)
                    ->sum('amount');

                if ($collected >= $schedule->total_amount) {
                    $schedule->update(['status' => ChargeScheduleStatus::Completed]);
                }
            });
        }
    }
}
