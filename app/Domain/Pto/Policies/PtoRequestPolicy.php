<?php

namespace App\Domain\Pto\Policies;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Models\PtoRequest;

/**
 * PTO approval/cancellation authorization (ADR-0016). Approval requires
 * `workflows.pto.approve`, with the HR self-approval guardrail: HR (when not also
 * admin/super_admin) cannot decide their own request — it routes to admin/super_admin.
 */
class PtoRequestPolicy
{
    public function approve(Person $user, PtoRequest $request): bool
    {
        if (! $user->can('workflows.pto.approve')) {
            return false;
        }

        $isSelf = $user->id === $request->person_id;
        $hrOnly = $user->hasRole('hr') && ! $user->hasAnyRole(['admin', 'super_admin']);

        return ! ($isSelf && $hrOnly);
    }

    public function reject(Person $user, PtoRequest $request): bool
    {
        return $this->approve($user, $request);
    }

    public function cancel(Person $user, PtoRequest $request): bool
    {
        if ($user->id === $request->person_id) {
            return true; // anyone may cancel their own request
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        // An approver cancelling someone else's request.
        return $user->can('workflows.pto.cancel_others');
    }
}
