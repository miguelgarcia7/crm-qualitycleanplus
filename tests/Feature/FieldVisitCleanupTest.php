<?php

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Shared\Models\File;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo('2026-10-13 15:00:00');
});

it('closes a terminated recruiter’s open visit, marked closed late', function () {
    $ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter', 'status' => PersonStatus::StaffActive]);
    $open = FieldVisit::factory()->create(['person_id' => $ray->id, 'check_in_at' => '2026-10-12 13:14:00']);
    $admin = person('admin');

    $this->actingAs($admin)->post(main('/admin/terminations'), [
        'person_id' => $ray->id,
        'effective_date' => '2026-10-13',
        'termination_type' => 'voluntary',
        'reason_category' => 'resignation',
        'rehireable' => true,
    ])->assertRedirect();

    $open->refresh();
    expect($open->status)->toBe(FieldVisitStatus::Closed)
        ->and($open->was_late_close)->toBeTrue()
        ->and($open->closed_by_person_id)->toBe($admin->id);
    $this->assertDatabaseHas('activity_log', ['subject_id' => $open->id, 'event' => 'closed_on_termination', 'description' => 'Closed because Ray Recruiter was terminated']);
});

it('deletes selfies and GPS a year on, keeps the visit, and skips legal holds', function () {
    $selfie = fn (FieldVisit $v): File => tap(File::create([
        'fileable_type' => $v->getMorphClass(), 'fileable_id' => $v->id, 'disk' => 'local', 'path' => "selfies/{$v->id}.jpg",
        'original_name' => 'selfie.jpg', 'mime_type' => 'image/jpeg', 'size' => 10,
    ]), function (File $f) use ($v): void {
        Storage::disk('local')->put($f->path, 'jpg');
        $v->update(['check_in_selfie_file_id' => $f->id]);
    });
    $gps = ['check_in_gps_lat' => 41.88, 'check_in_gps_lng' => -87.63, 'check_in_gps_accuracy_meters' => 12, 'check_out_gps_lat' => 41.88, 'check_out_gps_lng' => -87.63];

    $old = FieldVisit::factory()->closed()->create(['check_in_at' => '2025-09-01 15:00:00'] + $gps);
    $oldFile = $selfie($old);
    $recent = FieldVisit::factory()->closed()->create(['check_in_at' => '2026-01-10 15:00:00'] + $gps);
    $selfie($recent);
    $held = FieldVisit::factory()->closed()->create(['check_in_at' => '2025-08-01 15:00:00'] + $gps);
    $held->person->setLegalHold('Lawsuit');
    $selfie($held);

    $this->artisan('field-visits:purge')->expectsOutput('Purged selfies and GPS from 1 visit.')->assertSuccessful();

    $old->refresh();
    expect($old->exists)->toBeTrue()
        ->and($old->check_in_selfie_file_id)->toBeNull()
        ->and($old->check_in_gps_lat)->toBeNull()
        ->and($old->check_out_gps_lng)->toBeNull()
        ->and($old->check_in_gps_accuracy_meters)->toBeNull()
        ->and(File::withTrashed()->find($oldFile->id))->toBeNull()
        ->and(Storage::disk('local')->exists($oldFile->path))->toBeFalse()
        ->and($recent->fresh()->check_in_gps_lat)->not->toBeNull()
        ->and($held->fresh()->check_in_selfie_file_id)->not->toBeNull();
});

it('flags hotels no recruiter has visited in 14 days on Needs attention', function () {
    $rita = tap(person('recruiter'))->update(['name' => 'Rita Recruiter']);
    $assign = function (string $name) use ($rita): Property {
        $p = Property::factory()->create(['name' => $name]);
        $p->assignments()->create(['person_id' => $rita->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

        return $p;
    };
    $stale = $assign('QCP Property');
    $fresh = $assign('Acme Hotel');
    $never = $assign('Lakeview Suites');
    $nobody = Property::factory()->create(['name' => 'No Recruiter Inn']);
    FieldVisit::factory()->closed()->create(['person_id' => $rita->id, 'property_id' => $stale->id, 'check_in_at' => '2026-09-20 15:00:00']);
    FieldVisit::factory()->closed()->create(['person_id' => $rita->id, 'property_id' => $stale->id, 'check_in_at' => '2026-09-01 15:00:00']);
    FieldVisit::factory()->closed()->create(['person_id' => $rita->id, 'property_id' => $fresh->id, 'check_in_at' => '2026-10-09 15:00:00']);

    $visitsItem = fn (Property $p, $user) => collect($this->actingAs($user)->get(main("/admin/properties/{$p->id}"))
        ->viewData('page')['props']['overview']['attention'])->firstWhere('key', 'visits');

    $om = person('office_manager');
    expect($visitsItem($stale, $om))->toMatchArray([
        'title' => 'No recruiter visit in 23 days',
        'detail' => 'Last visit Sep 20 by Rita Recruiter.',
        'action' => ['label' => 'Visits', 'tab' => 'visits'],
    ])
        ->and($visitsItem($never, $om)['title'])->toBe('No recruiter visit yet')
        ->and($visitsItem($never, $om)['detail'])->toBe("Rita Recruiter is assigned but hasn't checked in here.")
        ->and($visitsItem($fresh, $om))->toBeNull()
        ->and($visitsItem($nobody, $om))->toBeNull()
        // Payroll can't see visits, so the line isn't theirs.
        ->and($visitsItem($stale, person('payroll')))->toBeNull();
});
