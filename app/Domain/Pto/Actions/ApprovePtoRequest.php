<?php

namespace App\Domain\Pto\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use Illuminate\Validation\ValidationException;

/**
 * Approves a pending PTO request (ADR-0016). Authorization (incl. the HR
 * self-approval guardrail) is enforced by PtoRequestPolicy before this runs;
 * admin/super_admin self-approval is recorded via `is_self_approved`.
 */
class ApprovePtoRequest
{
    public function handle(PtoRequest $request, Person $approver): PtoRequest
    {
        if ($request->status !== PtoRequestStatus::Pending) {
            throw ValidationException::withMessages(['pto' => 'Only a pending request can be approved.']);
        }

        $request->update([
            'status' => PtoRequestStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->id,
            'is_self_approved' => $request->person_id === $approver->id,
        ]);

        return $request;
    }
}
