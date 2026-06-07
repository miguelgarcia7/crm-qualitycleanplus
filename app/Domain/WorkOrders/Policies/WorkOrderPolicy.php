<?php

namespace App\Domain\WorkOrders\Policies;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\WorkOrders\Models\WorkOrder;

/**
 * Work-order authorization (10-architecture/permissions-matrix.md). Recruiters
 * act on WOs for properties they're assigned to; office_manager/admin global.
 */
class WorkOrderPolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('work_orders.view');
    }

    public function view(Person $user, WorkOrder $workOrder): bool
    {
        return $this->passes($user, $workOrder, 'work_orders.view');
    }

    public function create(Person $user): bool
    {
        return $user->can('work_orders.create');
    }

    public function update(Person $user, WorkOrder $workOrder): bool
    {
        return $this->passes($user, $workOrder, 'work_orders.edit');
    }

    public function close(Person $user, WorkOrder $workOrder): bool
    {
        return $this->passes($user, $workOrder, 'work_orders.close');
    }

    public function delete(Person $user, WorkOrder $workOrder): bool
    {
        return $this->passes($user, $workOrder, 'work_orders.edit');
    }

    /**
     * Permission check + "(own)" scoping: global roles pass; everyone else must
     * hold the permission AND be assigned to the WO's property.
     */
    private function passes(Person $user, WorkOrder $workOrder, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return true;
        }

        return $workOrder->property !== null && $user->isAssignedTo($workOrder->property);
    }
}
