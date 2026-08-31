<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Actions\AllocateChargeScheduleEntries;
use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adjusting a contractor's scheduled deductions — the hiring fee and uniform
 * charges. The total is fixed at creation; what changes here is how fast it is
 * collected, or whether it is collected at all.
 */
class ContractorChargeController extends Controller
{
    /**
     * Change the per-period amount. Only unapplied payments are rebuilt —
     * money already deducted from a paycheck is history.
     */
    public function update(Request $request, ContractorChargeSchedule $schedule, AllocateChargeScheduleEntries $allocate): RedirectResponse
    {
        // Permission alone is not enough: recruiters hold
        // time_entries.add_adjustment but are scoped to their own contractors
        // (ADR-0019), so check reach to this person as well.
        $this->authorize('time_entries.add_adjustment');
        $this->authorize('view', $schedule->person);

        $validated = $request->validate([
            'amount_per_payment' => ['required', 'integer', 'min:1', 'max:'.$schedule->total_amount],
        ]);

        if ($schedule->status !== ChargeScheduleStatus::Active) {
            throw ValidationException::withMessages([
                'amount_per_payment' => 'This schedule is no longer active.',
            ]);
        }

        DB::transaction(function () use ($schedule, $validated): void {
            $collected = (int) $schedule->entries()
                ->where('status', ChargeEntryStatus::Applied->value)
                ->sum('amount');

            $perPayment = (int) $validated['amount_per_payment'];
            $remaining = $schedule->total_amount - $collected;

            // Drop only what has not been taken yet, then let the allocator
            // rebuild at the new rate against currently open periods.
            $schedule->entries()->where('status', ChargeEntryStatus::Scheduled->value)->delete();

            $schedule->update([
                'amount_per_payment' => $perPayment,
                'num_payments' => $schedule->entries()->count() + (int) ceil(max(0, $remaining) / $perPayment),
            ]);
        });

        $allocate->handle($schedule->fresh());

        return back()->with('success', 'Deduction amount updated.');
    }

    /**
     * Stop collecting. Applied payments stand; everything scheduled is dropped,
     * so the contractor is not charged the remainder.
     */
    public function cancel(ContractorChargeSchedule $schedule): RedirectResponse
    {
        // Permission alone is not enough: recruiters hold
        // time_entries.add_adjustment but are scoped to their own contractors
        // (ADR-0019), so check reach to this person as well.
        $this->authorize('time_entries.add_adjustment');
        $this->authorize('view', $schedule->person);

        DB::transaction(function () use ($schedule): void {
            $schedule->entries()->where('status', ChargeEntryStatus::Scheduled->value)->delete();
            $schedule->update(['status' => ChargeScheduleStatus::Cancelled]);
        });

        return back()->with('success', 'Deduction cancelled. Payments already taken are unchanged.');
    }
}
