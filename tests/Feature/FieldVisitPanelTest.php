<?php

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo('2026-10-13 20:20:00'); // 3:20 PM Central
    $this->hotel = Property::factory()->create(['name' => 'MAG Solutions', 'timezone' => 'America/Chicago', 'latitude' => 41.8781, 'longitude' => -87.6298, 'geofence_radius_meters' => 150]);
    $this->ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter']);
    $this->hotel->assignments()->create(['person_id' => $this->ray->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
});

/** Ray checks in through the real endpoint, about 640 m north of the hotel, yesterday morning. */
function rayChecksIn($test): FieldVisit
{
    $test->travelTo('2026-10-12 13:14:00'); // 8:14 AM Central
    $test->actingAs($test->ray)->post(main('/admin/field-visits'), [
        'property_id' => $test->hotel->id, 'gps_status' => 'ok', 'lat' => 41.8839, 'lng' => -87.6298, 'accuracy' => 12,
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertSessionHasNoErrors();
    $test->travelTo('2026-10-13 20:20:00');

    return FieldVisit::query()->latest('id')->firstOrFail();
}

it('opens a visit in the side panel with the selfie, where they stood, and its History', function () {
    $visit = rayChecksIn($this);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/field-visits?panel={$visit->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('panel.recruiter', 'Ray Recruiter')
            ->where('panel.open', true)
            ->where('panel.open_hours', 31)
            ->where('panel.off_site', true)
            ->where('panel.in', 'Mon, Oct 12 · 8:14 AM')
            ->where('panel.check_in.distance', 645)
            ->where('panel.check_in.accuracy', 12)
            ->where('panel.radius', 150)
            ->where('panel.selfie_url', "/admin/field-visits/{$visit->id}/selfie")
            ->where('panel.history.0.text', 'Checked in at MAG Solutions (645 m from the hotel)')
            ->where('panel.history.0.by', 'Ray Recruiter')
            ->where('panel.can', ['close' => true, 'fix' => false]));

    $this->actingAs(person('office_manager'))->get(main("/admin/field-visits/{$visit->id}/selfie"))->assertOk();
});

it('lets a recruiter open only their own visits, without office actions', function () {
    $visit = rayChecksIn($this);
    $rita = person('recruiter');

    $this->actingAs($this->ray)->get(main("/admin/field-visits?panel={$visit->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('panel.id', $visit->id)->where('panel.can', ['close' => false, 'fix' => false]));

    $this->actingAs($rita)->get(main("/admin/field-visits?panel={$visit->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('panel', null));
    $this->actingAs($rita)->get(main("/admin/field-visits/{$visit->id}/selfie"))->assertForbidden();
});

it('lets the office close a visit someone forgot, at the time they left', function () {
    $visit = rayChecksIn($this);
    $hr = tap(person('hr'))->update(['name' => 'Hannah HR']);

    // Not before they checked in.
    $this->actingAs($hr)->post(main("/admin/field-visits/{$visit->id}/close"), ['left_at' => '2026-10-12T07:00'])
        ->assertSessionHasErrors('left_at');

    $this->actingAs($hr)->post(main("/admin/field-visits/{$visit->id}/close"), ['left_at' => '2026-10-12T09:00', 'note' => 'Ray says he left around 9.'])
        ->assertSessionHasNoErrors();

    $visit->refresh();
    expect($visit->status)->toBe(FieldVisitStatus::Closed)
        ->and($visit->check_out_at->toDateTimeString())->toBe('2026-10-12 14:00:00')
        ->and($visit->was_late_close)->toBeTrue()
        ->and($visit->closed_by_person_id)->toBe($hr->id);

    $this->actingAs($hr)->get(main("/admin/field-visits?panel={$visit->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('panel.closed_by', 'Hannah HR')
            ->where('panel.minutes', 46)
            ->where('panel.can.close', false)
            ->where('panel.history.1.text', 'Closed by the office: left at Oct 12, 9:00 AM. Ray says he left around 9.'));

    // Once closed, it stays closed; and recruiters or payroll can't close anyone's.
    $this->actingAs($hr)->post(main("/admin/field-visits/{$visit->id}/close"), ['left_at' => '2026-10-12T10:00'])->assertForbidden();
    $open = FieldVisit::factory()->create(['property_id' => $this->hotel->id]);
    $this->actingAs($this->ray)->post(main("/admin/field-visits/{$open->id}/close"), ['left_at' => '2026-10-13T10:00'])->assertForbidden();
    $this->actingAs(person('payroll'))->post(main("/admin/field-visits/{$open->id}/close"), ['left_at' => '2026-10-13T10:00'])->assertForbidden();
});

it('lets only super admin fix a visit’s times, with a reason kept on History', function () {
    $visit = FieldVisit::factory()->closed()->create([
        'property_id' => $this->hotel->id, 'person_id' => $this->ray->id,
        'check_in_at' => '2026-10-09 14:05:00', 'check_out_at' => '2026-10-09 14:52:00',
    ]);
    $payload = ['check_in_at' => '2026-10-09T08:55', 'check_out_at' => '2026-10-09T09:52', 'reason' => 'Phone clock was off'];

    $this->actingAs(person('admin'))->put(main("/admin/field-visits/{$visit->id}/times"), $payload)->assertForbidden();
    $this->actingAs(person('super_admin'))->put(main("/admin/field-visits/{$visit->id}/times"), ['reason' => ''] + $payload)->assertSessionHasErrors('reason');
    $this->actingAs(person('super_admin'))->put(main("/admin/field-visits/{$visit->id}/times"), ['check_out_at' => '2026-10-09T08:00'] + $payload)->assertSessionHasErrors('check_out_at');

    $this->actingAs(person('super_admin'))->put(main("/admin/field-visits/{$visit->id}/times"), $payload)->assertSessionHasNoErrors();

    expect($visit->fresh()->check_in_at->toDateTimeString())->toBe('2026-10-09 13:55:00')
        ->and($visit->fresh()->durationMinutes())->toBe(57);
    $this->actingAs(person('super_admin'))->get(main("/admin/field-visits?panel={$visit->id}"))
        ->assertInertia(fn (Assert $page) => $page->where(
            'panel.history.0.text',
            'Times fixed from in Oct 9, 9:05 AM, out Oct 9, 9:52 AM to in Oct 9, 8:55 AM, out Oct 9, 9:52 AM. Reason: Phone clock was off',
        ));
});

it('opens a visit from the hotel’s Visits tab, only one of that hotel’s', function () {
    $visit = rayChecksIn($this);
    $elsewhere = FieldVisit::factory()->create();

    $this->actingAs(person('admin'))->get(main("/admin/properties/{$this->hotel->id}?panel={$visit->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('visitPanel.id', $visit->id));
    $this->actingAs(person('admin'))->get(main("/admin/properties/{$this->hotel->id}?panel={$elsewhere->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('visitPanel', null));
});
