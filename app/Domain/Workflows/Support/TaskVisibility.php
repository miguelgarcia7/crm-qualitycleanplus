<?php

namespace App\Domain\Workflows\Support;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Which role-assigned tasks a person may see and act on. A step assigned to
 * "recruiter" goes to the role, but a pay increase belongs to one property:
 * only that property's recruiters (and the global roles) get it. Shared by the
 * My Tasks query and WorkflowPolicy::act, so what's listed is what's allowed.
 */
final class TaskVisibility
{
    /** Workflow types whose tasks follow the work order's property. */
    private const PROPERTY_SCOPED = [WorkflowType::PayIncrease];

    /**
     * Narrows a workflows query to the ones this person may handle.
     *
     * @param  Builder<Model>  $workflows  a query on `workflows`
     */
    public static function scope(Builder $workflows, Person $person): void
    {
        if ($person->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        $types = array_map(fn (WorkflowType $t) => $t->value, self::PROPERTY_SCOPED);
        $propertyIds = $person->assignedProperties()->pluck('properties.id')->all();

        $workflows->where(fn (Builder $q) => $q
            ->whereNotIn('type', $types)
            ->orWhereIn('data->work_order_id', WorkOrder::query()->select('id')->whereIn('property_id', $propertyIds)));
    }

    public static function allows(Person $person, Workflow $workflow): bool
    {
        if (! in_array($workflow->workflowType(), self::PROPERTY_SCOPED, true) || $person->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return true;
        }

        $property = WorkOrder::find($workflow->data['work_order_id'] ?? 0)?->property;

        return $property !== null && $person->isAssignedTo($property);
    }
}
