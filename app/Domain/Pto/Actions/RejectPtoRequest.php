<?php

namespace App\Domain\Pto\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use Illuminate\Validation\ValidationException;

/**
 * Rejects a pending PTO request (ADR-0016) — the reserved hours return to available.
 */
class RejectPtoRequest
{
    public function handle(PtoRequest $request, Person $approver, string $reason): PtoRequest
    {
        if ($request->status !== PtoRequestStatus::Pending) {
            throw ValidationException::withMessages(['pto' => 'Only a pending request can be rejected.']);
        }

        $request->update([
            'status' => PtoRequestStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $approver->id,
            'reject_reason' => $reason,
        ]);

        return $request;
    }
}
