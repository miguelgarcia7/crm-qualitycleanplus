<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Services\DashboardMetrics;
use App\Domain\People\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The back-office landing dashboard — a role-aware set of widgets (Phase 06)
 * assembled by {@see DashboardMetrics} from the viewer's roles + permissions.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        return Inertia::render('admin/dashboard/index', [
            'widgets' => $metrics->forBackOffice($user),
        ]);
    }
}
