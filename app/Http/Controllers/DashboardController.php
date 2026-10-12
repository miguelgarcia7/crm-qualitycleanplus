<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Services\AdminToday;
use App\Domain\Dashboards\Services\DashboardMetrics;
use App\Domain\People\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The back-office landing dashboard. Admins get the role dashboard designed in
 * docs/50-ui/dashboard-mockups; the other roles keep the Phase 06 widgets
 * ({@see DashboardMetrics}) until their own view is built.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics, AdminToday $adminToday): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return Inertia::render('admin/dashboard/admin-today', [
                'today' => $adminToday->build($user),
            ]);
        }

        return Inertia::render('admin/dashboard/index', [
            'widgets' => $metrics->forBackOffice($user),
        ]);
    }
}
