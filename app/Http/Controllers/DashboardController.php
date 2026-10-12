<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Services\AdminAnalytics;
use App\Domain\Dashboards\Services\AdminToday;
use App\Domain\Dashboards\Services\AdminWorkforce;
use App\Domain\Dashboards\Services\DashboardMetrics;
use App\Domain\Dashboards\Services\FrontDeskHome;
use App\Domain\Dashboards\Services\HrHome;
use App\Domain\Dashboards\Services\OfficeHome;
use App\Domain\Dashboards\Services\PayrollHome;
use App\Domain\Dashboards\Services\RecruiterHome;
use App\Domain\People\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The back-office landing dashboard. Admins get the role dashboard designed in
 * docs/50-ui/dashboard-mockups, as tabs (Today, Analytics, Workforce) picked with
 * ?view=; every other back-office role (office manager, front desk, HR, payroll, recruiter) gets its own page; the other roles keep the Phase 06 widgets ({@see DashboardMetrics})
 * until their own view is built.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics, AdminToday $today, AdminAnalytics $analytics, AdminWorkforce $workforce, RecruiterHome $recruiter, PayrollHome $payroll, HrHome $hr, OfficeHome $office, FrontDeskHome $frontDesk): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        if (! $user->hasAnyRole(['super_admin', 'admin']) && $user->hasRole('office_manager')) {
            return Inertia::render('admin/dashboard/office', ['home' => $office->build($user)]);
        }

        if (! $user->hasAnyRole(['super_admin', 'admin']) && $user->hasRole('front_desk')) {
            return Inertia::render('admin/dashboard/front-desk', ['home' => $frontDesk->build($user)]);
        }

        if (! $user->hasAnyRole(['super_admin', 'admin']) && $user->hasRole('hr')) {
            return Inertia::render('admin/dashboard/hr', ['home' => $hr->build($user)]);
        }

        if (! $user->hasAnyRole(['super_admin', 'admin']) && $user->hasRole('payroll')) {
            return Inertia::render('admin/dashboard/payroll', [
                'home' => $payroll->build($user, $request->query('week') !== null ? (string) $request->query('week') : null),
            ]);
        }

        if (! $user->hasAnyRole(['super_admin', 'admin']) && $user->hasRole('recruiter')) {
            return Inertia::render('admin/dashboard/recruiter', [
                'home' => $recruiter->build($user),
            ]);
        }

        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            return Inertia::render('admin/dashboard/index', [
                'widgets' => $metrics->forBackOffice($user),
            ]);
        }

        $tabs = [['key' => 'today', 'label' => 'Today', 'href' => '/admin/dashboard']];
        if ($user->can('reports.financial.view')) {
            $tabs[] = ['key' => 'analytics', 'label' => 'Analytics', 'href' => '/admin/dashboard?view=analytics'];
        }
        if ($user->can('people.contractors.view')) {
            $tabs[] = ['key' => 'workforce', 'label' => 'Workforce', 'href' => '/admin/dashboard?view=workforce'];
        }

        $view = collect($tabs)->contains('key', $request->query('view')) ? (string) $request->query('view') : 'today';
        $shared = ['header' => $today->header($user), 'tabs' => $tabs, 'view' => $view];

        if ($view === 'analytics') {
            return Inertia::render('admin/dashboard/admin-analytics', [
                ...$shared,
                'analytics' => $analytics->build((string) $request->query('period', 'mtd')),
            ]);
        }

        if ($view === 'workforce') {
            return Inertia::render('admin/dashboard/admin-workforce', [
                ...$shared,
                'workforce' => $workforce->build(),
            ]);
        }

        return Inertia::render('admin/dashboard/admin-today', [
            ...$shared,
            'today' => $today->build($user),
        ]);
    }
}
