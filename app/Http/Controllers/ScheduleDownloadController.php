<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\WeekSchedule;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\PropertyBible\Support\ScheduleDownload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back office: download a week's schedule a property manager uploaded — the
 * link in the recruiter's "schedule uploaded" notice. One file as it is, or
 * the week's files as a zip. Gated `schedules.view`, scoped to the user's hotels.
 */
class ScheduleDownloadController extends Controller
{
    public function week(Request $request, WeekSchedule $weekSchedule, ScheduleDownload $download): Response|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('schedules.view'), 403);
        abort_unless($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES) || $user->isAssignedTo($weekSchedule->property), 403);

        // Opened from the bell, an Inertia visit: hand it to the browser so
        // the file downloads instead of landing in a page.
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        return $download->week($weekSchedule);
    }
}
