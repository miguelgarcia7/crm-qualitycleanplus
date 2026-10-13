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
use App\Domain\People\Support\StaffRoles;
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

        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            // Someone with two office roles (HR and Recruiter, say) gets a switch
            // between their dashboards; the last one they picked is remembered.
            $roles = array_values(array_filter(StaffRoles::DASHBOARDS, fn (string $r): bool => $user->hasRole($r)));
            $role = $this->pickRole($request, $roles);
            $switch = ['dashboard_switch' => count($roles) > 1 ? [
                'current' => $role,
                'options' => array_map(fn (string $r): array => ['key' => $r, 'label' => StaffRoles::DASHBOARD_LABELS[$r], 'href' => "/admin/dashboard?as={$r}"], $roles),
            ] : null];

            return match ($role) {
                'office_manager' => Inertia::render('admin/dashboard/office', ['home' => $office->build($user), ...$switch]),
                'front_desk' => Inertia::render('admin/dashboard/front-desk', ['home' => $frontDesk->build($user), ...$switch]),
                'hr' => Inertia::render('admin/dashboard/hr', ['home' => $hr->build($user), ...$switch]),
                'payroll' => Inertia::render('admin/dashboard/payroll', [
                    'home' => $payroll->build($user, $request->query('week') !== null ? (string) $request->query('week') : null),
                    ...$switch,
                ]),
                'recruiter' => Inertia::render('admin/dashboard/recruiter', ['home' => $recruiter->build($user), ...$switch]),
                default => Inertia::render('admin/dashboard/index', ['widgets' => $metrics->forBackOffice($user)]),
            };
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

    /**
     * The dashboard to show someone with these roles: the one asked for, else
     * the one they last picked, else the first in StaffRoles::DASHBOARDS order.
     *
     * @param  list<string>  $roles
     */
    private function pickRole(Request $request, array $roles): ?string
    {
        $asked = $request->query('as');
        if (is_string($asked) && in_array($asked, $roles, true)) {
            $request->session()->put('dashboard_as', $asked);

            return $asked;
        }

        $last = $request->session()->get('dashboard_as');

        return in_array($last, $roles, true) ? $last : ($roles[0] ?? null);
    }
}
