<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Recruiter-side timesheet action (back office): send a week for PM approval.
 */
class TimesheetController extends Controller
{
    public function submit(Timesheet $timesheet, SubmitTimesheetForApproval $action): RedirectResponse
    {
        $this->authorize('submit', $timesheet);

        $user = Auth::user();
        abort_unless($user instanceof Person, 403);

        $action->handle($timesheet, $user);

        return back()->with('success', 'Timesheet sent for approval.');
    }
}
