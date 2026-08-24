<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\PunchFlagged;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

// Property at a known point (Phoenix) with QR enabled, a recruiter, and a
// contractor on an active WO + open period.
function clockScenario(string $phone = '(555) 222-3333'): array
{
    $property = Property::factory()->create([
        'timezone' => 'America/Phoenix', 'latitude' => 33.4484, 'longitude' => -112.0740,
        'geofence_radius_meters' => 300, 'qr_clock_enabled' => true,
    ]);
    $position = Position::factory()->create(['name' => 'Housekeeper']);

    $recruiter = Person::factory()->create(['status' => PersonStatus::StaffActive]);
    $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    $weekStart = CarbonImmutable::now('America/Phoenix')->startOfWeek(CarbonImmutable::MONDAY);
    PayrollPeriod::factory()->create([
        'property_id' => $property->id, 'week_start' => $weekStart->toDateString(),
        'week_end' => $weekStart->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open,
    ]);

    $contractor = Person::factory()->create([
        'status' => PersonStatus::ContractorActive,
        'phone' => $phone, 'normalized_phone' => preg_replace('/\D/', '', $phone),
    ]);
    $workOrder = WorkOrder::factory()->create([
        'person_id' => $contractor->id, 'property_id' => $property->id, 'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'status' => WorkOrderStatus::Active,
    ]);

    return compact('property', 'position', 'contractor', 'workOrder', 'phone', 'recruiter')
        + ['token' => $property->qr_token];
}

/** @return array<string, mixed> */
function clockPayload(array $extra, float $lat = 33.4484, float $lng = -112.0740): array
{
    return $extra + ['lat' => $lat, 'lng' => $lng, 'accuracy' => 12, 'selfie' => UploadedFile::fake()->image('selfie.jpg')];
}

// --- QR token ---------------------------------------------------------------

it('mints an unguessable token the first time QR is enabled and keeps it on disable', function () {
    $property = Property::factory()->create(['latitude' => 33.0, 'longitude' => -112.0]);
    expect($property->qr_token)->toBeNull();

    $property->update(['qr_clock_enabled' => true]);
    $token = $property->fresh()->qr_token;
    expect($token)->not->toBeNull()->and(strlen($token))->toBe(24);

    // Disable + re-enable keeps the token so printed posters stay valid.
    $property->update(['qr_clock_enabled' => false]);
    $property->update(['qr_clock_enabled' => true]);
    expect($property->fresh()->qr_token)->toBe($token);
});

it('404s identically for an unknown token and a disabled property', function () {
    $s = clockScenario();
    $s['property']->update(['qr_clock_enabled' => false]);

    $this->get(qcminute('/clock-in/definitely-not-a-token-here'))->assertNotFound();
    $this->get(qcminute("/clock-in/{$s['token']}"))->assertNotFound();
});

it('serves the clock-in page by token, not by property id', function () {
    $s = clockScenario();

    $this->get(qcminute("/clock-in/{$s['token']}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/clock-in/index')
            ->where('property.token', $s['token'])
            ->where('property.name', $s['property']->name));

    $this->get(qcminute("/clock-in/{$s['property']->id}"))->assertNotFound();
});

// --- Lookup + happy paths ----------------------------------------------------

it('looks up a contractor by phone and lists their work orders', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['token']}/lookup"), ['phone' => $s['phone']])
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/clock-in/index')
            ->where('lookup.contractor', $s['contractor']->name)
            ->where('lookup.work_orders', fn ($wos) => collect($wos)->pluck('id')->all() === [$s['workOrder']->id]),
        );
});

it('shows a clear error for an unknown phone number', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['token']}/lookup"), ['phone' => '(999) 000-1111'])
        ->assertInertia(fn (AssertableInertia $page) => $page->where('lookup.error', fn ($e) => is_string($e) && $e !== ''));
});

it('clocks a contractor in inside the geofence with gps + selfie, unflagged', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload([
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
    ]))->assertInertia(fn (AssertableInertia $page) => $page->where('result.action', 'in')->where('result.flagged', false));

    $entry = TimeEntry::query()->firstOrFail();
    expect($entry->clock_method)->toBe('qr')
        ->and($entry->source->value)->toBe('clock_event')
        ->and($entry->end_at_utc)->toBeNull()
        ->and($entry->clock_in_gps_flag_reason)->toBeNull()
        ->and($entry->clock_in_selfie_file_id)->not->toBeNull()
        ->and((float) $entry->clock_in_gps_lat)->toEqualWithDelta(33.4484, 0.0001);

    Notification::assertNothingSent();
});

// --- GpsPolicy: flag-and-notify ----------------------------------------------

it('blocks clock-in only on a trusted fix outside the geofence', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload([
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
    ], lat: 34.5000, lng: -112.0740))->assertSessionHasErrors('gps');

    expect(TimeEntry::query()->count())->toBe(0);
});

it('clocks in without GPS, flags the entry, and notifies the recruiter', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['token']}/in"), [
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
        'gps_failure_reason' => 'permission_denied',
        'selfie' => UploadedFile::fake()->image('s.jpg'),
    ])->assertInertia(fn (AssertableInertia $page) => $page->where('result.action', 'in')->where('result.flagged', true));

    $entry = TimeEntry::query()->firstOrFail();
    expect($entry->clock_in_gps_flag_reason)->toBe('permission_denied');

    Notification::assertSentTo($s['recruiter'], PunchFlagged::class, function (PunchFlagged $n) {
        return $n->direction === 'in' && $n->reason === 'permission_denied';
    });
});

it('flags a fix too blunt for the fence instead of judging it', function () {
    $s = clockScenario(); // radius 300 → required accuracy = min(200, 150) = 150m

    // WAY outside the fence, but the reading is untrusted — flagged, not blocked.
    $this->post(qcminute("/clock-in/{$s['token']}/in"), [
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
        'lat' => 34.5000, 'lng' => -112.0740, 'accuracy' => 400,
        'selfie' => UploadedFile::fake()->image('s.jpg'),
    ])->assertInertia(fn (AssertableInertia $page) => $page->where('result.flagged', true));

    expect(TimeEntry::query()->firstOrFail()->clock_in_gps_flag_reason)->toBe('poor_accuracy');
});

it('flags a punch at a property with no configured location', function () {
    $s = clockScenario();
    $s['property']->forceFill(['latitude' => null, 'longitude' => null])->save();

    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload([
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
    ]))->assertInertia(fn (AssertableInertia $page) => $page->where('result.flagged', true));

    expect(TimeEntry::query()->firstOrFail()->clock_in_gps_flag_reason)->toBe('property_unconfigured');
});

it('rejects a phone that does not own the work order', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload([
        'phone' => '(111) 222-3333', 'work_order_id' => $s['workOrder']->id,
    ]))->assertSessionHasErrors('phone');
});

// --- Clock-out ----------------------------------------------------------------

it('clocks out, setting duration and recomputing the summary', function () {
    $s = clockScenario();
    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));
    $entry = TimeEntry::query()->firstOrFail();

    $this->post(qcminute("/clock-in/{$s['token']}/out"), clockPayload(['phone' => $s['phone'], 'time_entry_id' => $entry->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('result.action', 'out')->where('result.flagged', false));

    expect($entry->fresh()->end_at_utc)->not->toBeNull()
        ->and($entry->fresh()->duration_minutes)->not->toBeNull()
        ->and($entry->fresh()->clock_out_selfie_file_id)->not->toBeNull()
        ->and(TimeSummary::query()->where('work_order_id', $s['workOrder']->id)->exists())->toBeTrue();
});

it('never blocks clock-out — a trusted fix outside the fence flags it instead', function () {
    $s = clockScenario();
    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));
    $entry = TimeEntry::query()->firstOrFail();

    $this->post(qcminute("/clock-in/{$s['token']}/out"), clockPayload([
        'phone' => $s['phone'], 'time_entry_id' => $entry->id,
    ], lat: 34.5000, lng: -112.0740))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('result.action', 'out')->where('result.flagged', true));

    expect($entry->fresh()->clock_out_gps_flag_reason)->toBe('outside_geofence');

    Notification::assertSentTo($s['recruiter'], PunchFlagged::class, fn (PunchFlagged $n) => $n->direction === 'out');
});

it('blocks a second clock-in while one is open', function () {
    $s = clockScenario();
    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));

    $this->post(qcminute("/clock-in/{$s['token']}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]))
        ->assertSessionHasErrors('work_order_id');

    expect(TimeEntry::query()->count())->toBe(1);
});

it('surfaces GPS flags on the timesheet grid', function () {
    $s = clockScenario();
    $this->seed(RolePermissionSeeder::class);

    $this->post(qcminute("/clock-in/{$s['token']}/in"), [
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
        'gps_failure_reason' => 'no_fix',
        'selfie' => UploadedFile::fake()->image('s.jpg'),
    ]);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$s['property']->id}/grid"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.0.gps_flags', ['in: no GPS fix']));
});

it('supports a lunch break as two entries the same day', function () {
    $s = clockScenario();
    $url = "/clock-in/{$s['token']}";

    $this->post(qcminute("{$url}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));
    $first = TimeEntry::query()->firstOrFail();
    $this->post(qcminute("{$url}/out"), clockPayload(['phone' => $s['phone'], 'time_entry_id' => $first->id]));
    $this->post(qcminute("{$url}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));

    expect(TimeEntry::query()->count())->toBe(2)
        ->and(TimeEntry::query()->whereNull('end_at_utc')->count())->toBe(1);
});
