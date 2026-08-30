<?php

namespace App\Domain\WorkOrders\Actions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use App\Notifications\DirectHireEligible;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the recruiters behind a placement that it has passed its direct-hire
 * threshold, so QCP hears it before the hotel raises it.
 *
 * Called after each summary recompute. Guarded by `direct_hire_notified_at` so
 * it fires once per work order, not on every clock-out thereafter.
 */
class NotifyDirectHireEligible
{
    public function __construct(private readonly DirectHireProgress $progress) {}

    public function handle(WorkOrder $workOrder): void
    {
        if ($workOrder->direct_hire_notified_at !== null) {
            return;
        }

        $progress = $this->progress->for($workOrder);

        // An unrestricted work order has no moment to announce.
        if ($progress['unrestricted'] === true || $progress['eligible'] !== true) {
            return;
        }

        // Stamp before sending: a notification failure must not leave this
        // firing on every subsequent recompute.
        $workOrder->forceFill(['direct_hire_notified_at' => now()])->save();

        $recipients = $this->recruitersFor($workOrder);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DirectHireEligible($workOrder));
        }
    }

    /**
     * The contractor's primary recruiter, plus any recruiter assigned to the
     * property — whoever would actually field the hotel's call.
     *
     * @return Collection<int, Person>
     */
    private function recruitersFor(WorkOrder $workOrder): Collection
    {
        return Person::query()
            ->where(function ($query) use ($workOrder): void {
                $query->whereHas(
                    'propertyAssignments',
                    fn ($q) => $q->where('property_id', $workOrder->property_id)
                        ->where('role', PropertyAssignmentRole::Recruiter->value),
                );

                if ($workOrder->person?->primary_recruiter_id !== null) {
                    $query->orWhere('id', $workOrder->person->primary_recruiter_id);
                }
            })
            ->get();
    }
}
