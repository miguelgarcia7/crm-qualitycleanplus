<?php

namespace App\Domain\PropertyBible\Enums;

/**
 * The capacity in which a person is assigned to a property. Drives "(own)"
 * scoping in policies. See 10-architecture/permissions-matrix.md.
 */
enum PropertyAssignmentRole: string
{
    case Recruiter = 'recruiter';
    case PropertyManager = 'property_manager';

    public function label(): string
    {
        return match ($this) {
            self::Recruiter => 'Recruiter',
            self::PropertyManager => 'Property Manager',
        };
    }
}
