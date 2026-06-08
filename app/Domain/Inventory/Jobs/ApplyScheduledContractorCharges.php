<?php

namespace App\Domain\Inventory\Jobs;

use App\Domain\Adjustments\Enums\AdjustmentSourceType;
use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Inventory\Enums\ChargeEntryStatus;
use App\Domain\Inventory\Enums\ChargeScheduleStatus;
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
                    'source_type' => AdjustmentSourceType::SupplyRequest,
                    'source_id' => $schedule->source_request_id,
                    'value' => $entry->amount,
                    'type' => AdjustmentType::Deduction,
                    'is_billable' => false,
                    'notes' => 'Uniform charge (payment '.$entry->payment_index.' of '.$schedule->num_payments.')',
                ]);

                $entry->update([
                    'status' => ChargeEntryStatus::Applied,
                    'applied_adjustment_id' => $adjustment->id,
                ]);

                if ($schedule->entries()->where('status', ChargeEntryStatus::Scheduled)->doesntExist()) {
                    $schedule->update(['status' => ChargeScheduleStatus::Completed]);
                }
            });
        }
    }
}
