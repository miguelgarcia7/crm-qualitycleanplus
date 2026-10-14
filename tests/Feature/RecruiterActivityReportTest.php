<?php

use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo('2026-10-13 15:00:00');
    $this->rita = tap(person('recruiter'))->update(['name' => 'Rita Recruiter', 'status' => PersonStatus::StaffActive]);
    $this->ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter', 'status' => PersonStatus::StaffActive]);

    $hotel = function (string $name, $recruiter): Property {
        $p = Property::factory()->create(['name' => $name]);
        $p->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

        return $p;
    };
    $this->acme = $hotel('Acme Hotel', $this->rita);
    $this->qcp = $hotel('QCP Property', $this->rita);
    $this->mag = $hotel('MAG Solutions', $this->ray);
    $this->lake = $hotel('Lakeview Suites', $this->ray); // never visited

    $visit = fn ($who, Property $at, string $day, array $extra = []) => FieldVisit::factory()->closed()->create([
        'person_id' => $who->id, 'property_id' => $at->id,
        'check_in_at' => "{$day} 15:00:00", 'check_out_at' => "{$day} 15:45:00",
    ] + $extra);

    $visit($this->rita, $this->acme, '2026-10-09');
    $visit($this->rita, $this->acme, '2026-10-02', ['was_inside_geofence' => false]);
    $visit($this->ray, $this->mag, '2026-10-05');
    $visit($this->ray, $this->acme, '2026-10-06', ['was_covering' => true]);
    $visit($this->rita, $this->qcp, '2026-09-20'); // last month
    FieldVisit::factory()->create(['person_id' => $this->ray->id, 'property_id' => $this->mag->id, 'check_in_at' => '2026-10-12 13:14:00']); // still open
});

it('rolls up a month of visits per recruiter, week by week', function () {
    $this->actingAs(person('office_manager'))
        ->get(main('/admin/reports/recruiter-activity'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/recruiter-activity')
            ->where('label', 'October 2026')
            ->where('summary.visits', 5)
            ->where('summary.previous_visits', 1)
            ->where('summary.hotels_visited', 2)
            ->where('summary.hotels_assigned', 4)
            ->where('summary.avg_minutes', 45)
            ->where('summary.open_now', 1)
            ->where('weeks', ['Sep 28', 'Oct 5', 'Oct 12', 'Oct 19', 'Oct 26'])
            ->where('rows.0.name', 'Ray Recruiter')
            ->where('rows.0.visits', 3)
            ->where('rows.0.hotels_visited', 1)
            ->where('rows.0.hotels_assigned', 2)
            ->where('rows.0.not_visited', 1)
            ->where('rows.0.covering', 1)
            ->where('rows.0.open_now', 1)
            ->where('rows.0.by_week', [0, 2, 1, 0, 0])
            ->where('rows.1.name', 'Rita Recruiter')
            ->where('rows.1.visits', 2)
            ->where('rows.1.off_site', 1)
            ->where('rows.1.not_visited', 1)
            ->where('rows.1.by_week', [1, 1, 0, 0, 0])
            ->where('stale.0.hotel', 'Lakeview Suites')
            ->where('stale.0.days', null)
            ->where('stale.1', ['id' => $this->qcp->id, 'hotel' => 'QCP Property', 'recruiters' => 'Rita Recruiter', 'last' => 'Sun, Sep 20', 'days' => 23])
            ->where('next', null));
});

it('shows a week day by day, and narrows to one recruiter', function () {
    $om = person('office_manager');

    $this->actingAs($om)->get(main('/admin/reports/recruiter-activity?period=week&at=2026-10-07'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('label', 'Week of Oct 5, 2026')
            ->where('summary.visits', 3)
            ->where('summary.previous_visits', 1)
            ->where('weeks', ['Mon 5', 'Tue 6', 'Wed 7', 'Thu 8', 'Fri 9', 'Sat 10', 'Sun 11'])
            ->where('next', '2026-10-12'));

    $this->actingAs($om)->get(main("/admin/reports/recruiter-activity?recruiter_id={$this->rita->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('summary.visits', 2)
            ->where('summary.hotels_assigned', 2)
            ->has('stale', 1)
            ->where('stale.0.hotel', 'QCP Property'));
});

it('is in the catalog for the office, exports to Excel, and stays hidden from recruiters', function () {
    $hr = person('hr');

    $this->actingAs($hr)->get(main('/admin/reports'))
        ->assertInertia(fn (Assert $page) => $page->where('groups', fn ($groups) => collect($groups)->contains('group', 'Field visits')));
    $this->actingAs($hr)->get(main('/admin/reports/recruiter-activity/export'))->assertOk()->assertDownload('recruiter-activity-2026-10-01.xlsx');

    $this->actingAs($this->rita)->get(main('/admin/reports/recruiter-activity'))->assertForbidden();
    $this->actingAs($this->rita)->get(main('/admin/reports'))
        ->assertInertia(fn (Assert $page) => $page->where('groups', fn ($groups) => ! collect($groups)->contains('group', 'Field visits')));
});
