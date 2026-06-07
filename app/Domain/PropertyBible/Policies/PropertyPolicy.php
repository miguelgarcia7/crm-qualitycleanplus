<?php

namespace App\Domain\PropertyBible\Policies;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;

/**
 * Gates Property Bible access per 10-architecture/permissions-matrix.md.
 *
 * Most roles (super_admin, admin, office_manager, hr, payroll) see every
 * property. Recruiters and property managers are scoped to the properties
 * they're assigned to via `property_assignments` — the "(own)" rule.
 */
class PropertyPolicy
{
    /** Roles that see/act on ALL properties (not scoped to assignments). */
    public const GLOBAL_ROLES = ['super_admin', 'admin', 'office_manager', 'hr', 'payroll'];

    public function viewAny(Person $user): bool
    {
        return $user->can('bible.properties.view');
    }

    public function view(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.properties.view');
    }

    public function create(Person $user): bool
    {
        return $user->can('bible.properties.edit');
    }

    public function update(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.properties.edit');
    }

    public function delete(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.properties.edit');
    }

    // --- Bible sub-sections (called via $this->authorize('editDepartments', $property)) ---

    public function viewDepartments(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.departments.view');
    }

    public function editDepartments(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.departments.edit');
    }

    public function viewPositions(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.positions.view');
    }

    public function editPositions(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.positions.edit');
    }

    public function viewRates(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.rates.view');
    }

    public function editRates(Person $user, Property $property): bool
    {
        return $this->passes($user, $property, 'bible.rates.edit');
    }

    public function manageAssignments(Person $user, Property $property): bool
    {
        // Assigning recruiters/PMs to a property is an ownership-level edit.
        return $this->passes($user, $property, 'bible.properties.edit');
    }

    /**
     * Permission check + "(own)" scoping: global roles pass outright; everyone
     * else must hold the permission AND be assigned to the property.
     */
    private function passes(Person $user, Property $property, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        if ($user->hasAnyRole(self::GLOBAL_ROLES)) {
            return true;
        }

        return $user->isAssignedTo($property);
    }
}
