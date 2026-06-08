<?php

namespace App\Http\Controllers\Minute;

use App\Domain\Dashboards\Services\DashboardMetrics;
use App\Domain\People\Models\Person;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * QC Minute landing dashboard. Property managers get a tailored widget set
 * ({@see DashboardMetrics::forPropertyManager}); contractors (Phase 08) fall back
 * to the simple navigation cards.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        return Inertia::render('minute/dashboard/index', [
            'widgets' => $user->hasRole('property_manager') ? $metrics->forPropertyManager($user) : null,
        ]);
    }
}
