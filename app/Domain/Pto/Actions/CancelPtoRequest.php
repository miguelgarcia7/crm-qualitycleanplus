<?php

namespace App\Domain\Pto\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a pending or approved PTO request (ADR-0016) — reserved hours return to
 * available immediately. Authorization (requester / approver / super_admin) is
 * enforced by PtoRequestPolicy before this runs.
 */
class CancelPtoRequest
{
    public function handle(PtoRequest $request, Person $actor, ?string $reason = null): PtoRequest
    {
        if (! in_array($request->status, [PtoRequestStatus::Pending, PtoRequestStatus::Approved], true)) {
            throw ValidationException::withMessages(['pto' => 'Only a pending or approved request can be cancelled.']);
        }

        $request->update([
            'status' => PtoRequestStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancel_reason' => $reason,
        ]);

        return $request;
    }
}
