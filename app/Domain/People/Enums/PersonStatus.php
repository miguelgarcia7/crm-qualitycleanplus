<?php

namespace App\Domain\People\Enums;

/**
 * Lifecycle position of a person (ADR-0004, 20-domain/people-lifecycle.md).
 * Roles (Spatie) are independent of status.
 */
enum PersonStatus: string
{
    case Applicant = 'applicant';
    case ContractorActive = 'contractor_active';
    case ContractorInactive = 'contractor_inactive';
    case PendingTermination = 'pending_termination';
    case Terminated = 'terminated';
    case StaffActive = 'staff_active';
    case StaffInactive = 'staff_inactive';

    /** Has applied but not yet promoted to contractor. */
    public function isApplicant(): bool
    {
        return $this === self::Applicant;
    }

    /** Contractor-side statuses. */
    public function isContractor(): bool
    {
        return in_array($this, [self::ContractorActive, self::ContractorInactive], true);
    }

    /** W-2 staff statuses. */
    public function isStaff(): bool
    {
        return in_array($this, [self::StaffActive, self::StaffInactive], true);
    }

    /** Can this person currently work / clock in / be on new work orders? */
    public function isActive(): bool
    {
        return in_array($this, [self::ContractorActive, self::StaffActive], true);
    }
}
