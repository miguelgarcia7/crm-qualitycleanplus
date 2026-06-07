<?php

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Models\Invoice;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;

class InvoicePolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(Person $user, Invoice $invoice): bool
    {
        return $this->passes($user, $invoice, 'invoices.view');
    }

    public function send(Person $user, Invoice $invoice): bool
    {
        return $this->passes($user, $invoice, 'invoices.send');
    }

    private function passes(Person $user, Invoice $invoice, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return true;
        }

        return $invoice->property !== null && $user->isAssignedTo($invoice->property);
    }
}
