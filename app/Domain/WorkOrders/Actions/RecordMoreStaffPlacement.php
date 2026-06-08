<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\WorkflowNotice;
use Illuminate\Support\Facades\DB;

/**
 * Records a work-order placement against a more-staff request (ADR-0021):
 * recomputes `quantity_fulfilled`, transitions the request status, notifies the
 * PM, and completes the workflow's fulfill step once the request is fully met.
 * Idempotent and safe to call after any linked WO is created.
 */
class RecordMoreStaffPlacement
{
    public function __construct(private CompleteStep $completeStep) {}

    public function handle(WorkOrder $workOrder, ?Person $actor): void
    {
        if ($workOrder->more_staff_request_id === null) {
            return;
        }

        DB::transaction(function () use ($workOrder, $actor): void {
            $request = MoreStaffRequest::query()->lockForUpdate()->find($workOrder->more_staff_request_id);

            if ($request === null || ! $request->status->isOpen()) {
                return;
            }

            $fulfilled = $request->workOrders()->count();
            $request->quantity_fulfilled = $fulfilled;

            if ($fulfilled >= $request->quantity_requested) {
                $request->status = MoreStaffStatus::Fulfilled;
                $request->fulfilled_at ??= now();
                $request->save();

                $this->completeFulfillStep($request, $actor);

                $request->initiatedBy?->notify(new WorkflowNotice(
                    $request->workflow,
                    "Your staffing request for {$request->position?->name} at {$request->property?->name} is fully fulfilled ({$fulfilled} of {$request->quantity_requested}).",
                    ['more_staff_request_id' => $request->id],
                ));

                return;
            }

            $request->status = MoreStaffStatus::InProgress;
            $request->save();

            $request->initiatedBy?->notify(new WorkflowNotice(
                $request->workflow,
                "{$fulfilled} of {$request->quantity_requested} {$request->position?->name} placed at {$request->property?->name}.",
                ['more_staff_request_id' => $request->id],
            ));
        });
    }

    private function completeFulfillStep(MoreStaffRequest $request, ?Person $actor): void
    {
        $step = $request->workflow?->currentStep();

        if ($step === null || $step->step_key !== 'fulfill_staffing' || $step->status !== StepStatus::Pending) {
            return;
        }

        $resolved = $actor ?? $request->assignedRecruiter ?? $request->initiatedBy;

        if ($resolved === null) {
            return;
        }

        $this->completeStep->handle($step, $resolved);
    }
}
