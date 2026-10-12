<?php

use App\Domain\Dashboards\Services\DashboardMetrics;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** All widget titles across stats + lists + charts. */
function widgetTitles(mixed $widgets): Collection
{
    return collect($widgets['stats'] ?? [])
        ->merge($widgets['lists'] ?? [])
        ->merge($widgets['charts'] ?? [])
        ->pluck('title');
}

it('gives a recruiter property-scoped widgets and no office/payroll widgets', function () {
    // Recruiters land on their own dashboard now; the widget service still builds these.
    $recruiter = person('recruiter');
    $mine = Property::factory()->create();
    Property::factory()->create(); // someone else's
    $mine->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    $widgets = app(DashboardMetrics::class)->forBackOffice($recruiter);
    $titles = widgetTitles($widgets);

    expect($titles)->toContain('My open tasks', 'My properties', 'Weekly hours')
        ->not->toContain('Open periods')    // payroll-only
        ->not->toContain('Recent imports')  // office-only
        ->and(collect($widgets['stats'])->firstWhere('title', 'My properties')['value'])->toBe(1);
});

it('gives office manager the operations widgets and a revenue chart', function () {
    $this->actingAs(person('office_manager'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', function ($widgets) {
                $titles = widgetTitles($widgets);

                return $titles->contains('Properties')
                    && $titles->contains('Recent imports')
                    && $titles->contains('Low-stock items')
                    && $titles->contains('Weekly revenue');
            }),
        );
});

it('gives payroll period + invoice widgets', function () {
    // Payroll lands on its own dashboard now; the widget service still builds these.
    $titles = widgetTitles(app(DashboardMetrics::class)->forBackOffice(person('payroll')));

    expect($titles)->toContain('Open periods', 'Voided invoices', 'Invoices to send');
});

it('gives HR people + workflow widgets', function () {
    $this->actingAs(person('hr'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', function ($widgets) {
                $titles = widgetTitles($widgets);

                return $titles->contains('Pending info changes')
                    && $titles->contains('Applicants')
                    && $titles->contains('Terminations in progress');
            }),
        );
});

it('gives super admin cross-cutting widgets', function () {
    // Admins land on the Today view now; the widget service still builds these.
    $titles = widgetTitles(app(DashboardMetrics::class)->forBackOffice(person('super_admin')));

    expect($titles)->toContain('Active work orders', 'Open workflows', 'Invoiced this month');
});

it('never shows the same chart twice, whatever roles pile up', function () {
    // Revenue-vs-payouts is added once as the headline chart for anyone with
    // reports.financial.view; super_admin used to also add its own copy, which
    // rendered it at the top and again in the lower grid.
    $titles = collect(app(DashboardMetrics::class)->forBackOffice(person('super_admin'))['charts'])->pluck('title');

    expect($titles)->toContain('Revenue vs payouts (6 months)')
        ->and($titles->duplicates())->toBeEmpty();
});

it('includes the live ops widgets for timesheet-live viewers', function () {
    $this->actingAs(person('office_manager'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', function ($widgets) {
                $titles = widgetTitles($widgets);

                return is_array($widgets['onTheClock'])             // live clock-ins (an empty roster is fine)
                    && is_array($widgets['actions'])
                    && count($widgets['actions']) > 0
                    && $titles->contains('Hours this week');
            }),
        );
});

it('withholds live ops + activity widgets from roles without those permissions', function () {
    // HR has neither timesheets.view_live nor audit.activity_log.view.
    $this->actingAs(person('hr'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets.onTheClock', null)
            ->where('widgets.activity', null),
        );
});

it('only sends recent activity to audit viewers', function () {
    activity('testing')->log('Dashboard activity entry');

    $activity = app(DashboardMetrics::class)->forBackOffice(person('admin'))['activity'];

    expect(collect($activity)->pluck('description'))->toContain('Dashboard activity entry');
});

it('scopes quick actions to the role permissions', function () {
    $this->actingAs(person('front_desk'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', function ($widgets) {
                $labels = collect($widgets['actions'])->pluck('label');

                // Front desk can browse people but cannot create work orders or upload hours.
                return $labels->contains('People')
                    && ! $labels->contains('New Work Order')
                    && ! $labels->contains('Upload Hours');
            }),
        );
});

it('gives a property manager the QC Minute PM dashboard', function () {
    $this->actingAs(person('property_manager'))->get(qcminute('/'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('minute/dashboard/index')
            ->where('widgets', fn ($widgets) => $widgets !== null
                && widgetTitles($widgets)->contains('Timesheets to approve')),
        );
});

it('falls back to link-cards for a contractor on QC Minute', function () {
    $this->actingAs(person('contractor'))->get(qcminute('/'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('minute/dashboard/index')
            ->where('widgets', null),
        );
});
