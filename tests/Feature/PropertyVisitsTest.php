<?php

use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo('2026-10-13 15:00:00');
    $this->hotel = Property::factory()->create(['name' => 'Acme Hotel', 'timezone' => 'America/Chicago']);
    $this->rita = tap(person('recruiter'))->update(['name' => 'Rita Recruiter']);
    $this->ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter']);
    $this->hotel->assignments()->create(['person_id' => $this->rita->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
});

function visitAt(Property $hotel, $who, string $in, ?string $out, array $extra = []): FieldVisit
{
    return FieldVisit::factory()->create([
        'property_id' => $hotel->id,
        'person_id' => $who->id,
        'check_in_at' => $in,
        'check_out_at' => $out,
        'status' => $out === null ? 'open' : 'closed',
    ] + $extra);
}

it('shows managers how often recruiters visit, in the hotel’s own time', function () {
    visitAt($this->hotel, $this->rita, '2026-10-09 14:05:00', '2026-10-09 14:52:00'); // 9:05–9:52 AM Central
    visitAt($this->hotel, $this->rita, '2026-10-02 15:15:00', '2026-10-02 16:02:00', ['was_inside_geofence' => false]);
    visitAt($this->hotel, $this->ray, '2026-09-22 18:30:00', '2026-09-22 19:05:00', ['was_covering' => true]);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$this->hotel->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.scope', 'everyone')
            ->where('visits.stats.last', ['ago' => '4 days ago', 'date' => 'Fri, Oct 9', 'recruiter' => 'Rita Recruiter'])
            ->where('visits.stats.visits30', 3)
            ->where('visits.stats.avgMinutes30', 43)
            ->where('visits.stats.flagged30', ['off_site' => 1, 'late' => 0, 'covering' => 1])
            ->where('visits.recruiters.0.name', 'Ray Recruiter')
            ->where('visits.recruiters.0.assigned', false)
            ->where('visits.recruiters.1.name', 'Rita Recruiter')
            ->where('visits.recruiters.1.last', 'Oct 9')
            ->where('visits.recruiters.1.visits30', 2)
            ->where('visits.rows.0.in', '9:05 AM')
            ->where('visits.rows.0.out', '9:52 AM')
            ->where('visits.rows.0.minutes', 47)
            ->where('visits.counts', ['' => 3, 'off_site' => 1, 'late' => 0, 'covering' => 1]));
});

it('filters by flag, recruiter and dates, and pages on the server', function () {
    visitAt($this->hotel, $this->rita, '2026-10-09 14:05:00', '2026-10-09 14:52:00');
    visitAt($this->hotel, $this->ray, '2026-09-22 18:30:00', '2026-09-22 19:05:00', ['was_covering' => true]);
    visitAt($this->hotel, $this->rita, '2026-05-01 15:00:00', '2026-05-01 15:40:00'); // older than 90 days

    $admin = person('admin');
    $url = "/admin/properties/{$this->hotel->id}";

    $this->actingAs($admin)->get(main($url))
        ->assertInertia(fn (Assert $page) => $page->where('visits.filters.range', '90')->where('visits.total', 2));
    $this->actingAs($admin)->get(main("{$url}?visits_range=all"))
        ->assertInertia(fn (Assert $page) => $page->where('visits.total', 3));
    $this->actingAs($admin)->get(main("{$url}?visits_flag=covering"))
        ->assertInertia(fn (Assert $page) => $page->where('visits.total', 1)->where('visits.rows.0.recruiter', 'Ray Recruiter'));
    $this->actingAs($admin)->get(main("{$url}?visits_range=all&visits_recruiter={$this->rita->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('visits.total', 2));

    foreach (range(1, 16) as $i) {
        visitAt($this->hotel, $this->rita, sprintf('2026-10-01 %02d:00:00', $i), sprintf('2026-10-01 %02d:30:00', $i));
    }
    $this->actingAs($admin)->get(main("{$url}?visits_page=2"))
        ->assertInertia(fn (Assert $page) => $page->where('visits.current_page', 2)->has('visits.rows', 3));
});

it('says which day a late-closed visit was closed', function () {
    visitAt($this->hotel, $this->rita, '2026-09-18 14:10:00', '2026-09-19 13:02:00', ['was_late_close' => true]);

    $this->actingAs(person('admin'))->get(main("/admin/properties/{$this->hotel->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('visits.rows.0.out', 'Sep 19, 8:02 AM')->where('visits.rows.0.late', true));
});

it('shows a recruiter only their own visits', function () {
    visitAt($this->hotel, $this->rita, '2026-10-09 14:05:00', '2026-10-09 14:52:00');
    visitAt($this->hotel, $this->ray, '2026-10-08 14:05:00', '2026-10-08 14:52:00');

    $this->actingAs($this->rita)->get(main("/admin/properties/{$this->hotel->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.scope', 'own')
            ->where('visits.recruiters', null)
            ->where('visits.total', 1)
            ->where('visits.rows.0.recruiter', 'Rita Recruiter'));
});

it('hides the tab from roles that can’t see visits', function () {
    $this->actingAs(person('payroll'))->get(main("/admin/properties/{$this->hotel->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('visits', null));
});
