<?php

namespace App\Notifications;

use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Bus\Queueable;

/**
 * In-app notice that a contractor has worked enough hours at a property for
 * that property to hire them directly.
 *
 * QCP should know before the hotel asks: once the threshold passes, the
 * placement is no longer protected by the contract term, so the recruiter has a
 * window to have the conversation on their own terms.
 */
class DirectHireEligible extends AppNotification
{
    use Queueable;

    public function __construct(public WorkOrder $workOrder) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Workflows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $who = $this->workOrder->person->name;
        $where = $this->workOrder->property->name;
        $hours = (int) round(((int) $this->workOrder->direct_hire_threshold_minutes) / 60);

        return [
            'type' => 'direct_hire_eligible',
            'category' => $this->category()->value,
            'work_order_id' => $this->workOrder->id,
            'property_id' => $this->workOrder->property_id,
            'person_id' => $this->workOrder->person_id,
            'url' => '/admin/work-orders',
            'message' => "{$who} has passed {$hours} hours at {$where} and is now eligible for direct hire.",
        ];
    }
}
