<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Services\AdminAnalytics;
use App\Domain\Dashboards\Services\AdminToday;
use App\Domain\Dashboards\Services\DashboardMetrics;
use App\Domain\People\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The back-office landing dashboard. Admins get the role dashboard designed in
 * docs/50-ui/dashboard-mockups, as tabs (Today, Analytics) picked with
 * ?view=; the other roles keep the Phase 06 widgets ({@see DashboardMetrics})
 * until their own view is built.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics, AdminToday $today, AdminAnalytics $analytics): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            return Inertia::render('admin/dashboard/index', [
                'widgets' => $metrics->forBackOffice($user),
            ]);
        }

        $tabs = [['key' => 'today', 'label' => 'Today', 'href' => '/admin/dashboard']];
        if ($user->can('reports.financial.view')) {
            $tabs[] = ['key' => 'analytics', 'label' => 'Analytics', 'href' => '/admin/dashboard?view=analytics'];
        }

        $view = collect($tabs)->contains('key', $request->query('view')) ? (string) $request->query('view') : 'today';
        $shared = ['header' => $today->header($user), 'tabs' => $tabs, 'view' => $view];

        if ($view === 'analytics') {
            return Inertia::render('admin/dashboard/admin-analytics', [
                ...$shared,
                'analytics' => $analytics->build((string) $request->query('period', 'mtd')),
            ]);
        }

        return Inertia::render('admin/dashboard/admin-today', [
            ...$shared,
            'today' => $today->build($user),
        ]);
    }
}
