<?php

namespace App\Domain\Time\Concerns;

use App\Domain\People\Models\Person;
use Illuminate\Support\Facades\Auth;

/**
 * Audits changes to the numbers payroll and invoices are built from.
 *
 * Punches and adjustments are the source of billable and payable money, and
 * every step downstream of them is already audited — you can see who voided an
 * invoice, but until this you could not see who changed the hours it was built
 * from. There is no edit endpoint, so a correction is a delete plus a create:
 * both are logged, and the pair reads as the edit it really is.
 *
 * Logged against the CONTRACTOR rather than the entry, so it appears on their
 * profile History tab (which filters on subject = Person) as well as in the
 * audit log — and so a deleted entry's trail does not point at a row that no
 * longer exists.
 */
trait LogsPayrollActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    protected function logPayroll(?Person $person, string $event, string $description, array $properties = [], ?Person $actor = null): void
    {
        if ($person === null) {
            return;
        }

        activity('payroll')
            ->performedOn($person)
            ->causedBy($actor ?? Auth::user())
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }
}
