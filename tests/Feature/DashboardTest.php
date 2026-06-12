<?php

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
    $recruiter = person('recruiter');
    $mine = Property::factory()->create();
    Property::factory()->create(); // someone else's
    $mine->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    $this->actingAs($recruiter)->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/dashboard/index')
            ->where('widgets', function ($widgets) {
                $titles = widgetTitles($widgets);
                $myProperties = collect($widgets['stats'])->firstWhere('title', 'My properties');

                return $titles->contains('My open tasks')
                    && $titles->contains('My properties')
                    && $titles->contains('Weekly hours')
                    && ! $titles->contains('Open periods')        // payroll-only
                    && ! $titles->contains('Recent imports')      // office-only
                    && $myProperties['value'] === 1;              // scoped to the one assigned property
            }),
        );
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
    $this->actingAs(person('payroll'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', function ($widgets) {
                $titles = widgetTitles($widgets);

                return $titles->contains('Open periods')
                    && $titles->contains('Voided invoices')
                    && $titles->contains('Invoices to send');
            }),
        );
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
    $this->actingAs(person('super_admin'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', function ($widgets) {
                $titles = widgetTitles($widgets);

                return $titles->contains('Active work orders')
                    && $titles->contains('Open workflows')
                    && $titles->contains('Invoiced this month');
            }),
        );
});

it('includes the live ops widgets for timesheet-live viewers', function () {
    $this->actingAs(person('office_manager'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', function ($widgets) {
                $titles = widgetTitles($widgets);

                return is_array($widgets['clockedIn'])              // live clock-ins (empty array is fine)
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
            ->where('widgets.clockedIn', null)
            ->where('widgets.activity', null),
        );
});

it('only sends recent activity to audit viewers', function () {
    activity('testing')->log('Dashboard activity entry');

    $this->actingAs(person('admin'))->get(main('/admin/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('widgets', fn ($widgets) => is_array($widgets['activity'])
                && collect($widgets['activity'])->pluck('description')->contains('Dashboard activity entry')),
        );
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
