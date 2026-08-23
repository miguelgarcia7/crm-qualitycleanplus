<?php

use App\Domain\Devices\Models\Device;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A property (deliberately WITHOUT coordinates, to prove the tablet skips geofence),
 * an open period, a contractor on an active WO, and an activated paired device + token.
 *
 * @return array{device: Device, token: string, property: Property, workOrder: WorkOrder, phone: string}
 */
function tabletScenario(string $phone = '(555) 777-8888'): array
{
    $property = Property::factory()->create(['timezone' => 'America/Phoenix', 'latitude' => null, 'longitude' => null]);
    $position = Position::factory()->create();
    $weekStart = CarbonImmutable::now('America/Phoenix')->startOfWeek(CarbonImmutable::MONDAY);
    PayrollPeriod::factory()->create([
        'property_id' => $property->id, 'week_start' => $weekStart->toDateString(),
        'week_end' => $weekStart->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open,
    ]);

    $contractor = Person::factory()->create([
        'status' => PersonStatus::ContractorActive, 'phone' => $phone, 'normalized_phone' => preg_replace('/\D/', '', $phone),
    ]);
    $workOrder = WorkOrder::factory()->create([
        'person_id' => $contractor->id, 'property_id' => $property->id, 'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500, 'status' => WorkOrderStatus::Active,
    ]);

    $device = Device::factory()->activated()->create(['property_id' => $property->id]);
    $token = $device->createToken('test')->plainTextToken;

    return compact('device', 'token', 'property', 'workOrder', 'phone');
}

it('activates a device with a valid code and regenerates it', function () {
    $device = Device::factory()->create(['activation_code' => 'ABC123']);

    $response = $this->postJson(qcminute('/device/activate'), ['code' => 'ABC123'])->assertOk();
    expect($response->json('token'))->toBeString()
        ->and($device->fresh()->is_activated)->toBeTrue()
        ->and($device->fresh()->activation_code)->not->toBe('ABC123');
});

it('rejects an invalid activation code', function () {
    Device::factory()->create(['activation_code' => 'GOODBB']);

    $this->postJson(qcminute('/device/activate'), ['code' => 'NOPEEE'])->assertStatus(422);
});

it('looks up a contractor by phone using the device token', function () {
    $s = tabletScenario();

    $this->withHeader('Authorization', "Bearer {$s['token']}")
        ->postJson(qcminute('/device/lookup'), ['phone' => $s['phone']])
        ->assertOk()
        ->assertJsonPath('work_orders.0.id', $s['workOrder']->id);
});

it('clocks a contractor in with no GPS and no geofence check', function () {
    $s = tabletScenario(); // property has no coordinates

    $this->withHeader('Authorization', "Bearer {$s['token']}")
        ->post(qcminute('/device/clock-in'), [
            'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id, 'selfie' => UploadedFile::fake()->image('s.jpg'),
        ])->assertOk();

    $entry = TimeEntry::query()->firstOrFail();
    expect($entry->clock_method)->toBe('tablet')
        ->and($entry->clock_in_gps_lat)->toBeNull()
        ->and($entry->clock_in_selfie_file_id)->not->toBeNull()
        ->and($entry->end_at_utc)->toBeNull();
});

it('clocks a contractor out via the tablet', function () {
    $s = tabletScenario();
    $this->withHeader('Authorization', "Bearer {$s['token']}")->post(qcminute('/device/clock-in'), [
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id, 'selfie' => UploadedFile::fake()->image('s.jpg'),
    ]);
    $entry = TimeEntry::query()->firstOrFail();

    $this->withHeader('Authorization', "Bearer {$s['token']}")->post(qcminute('/device/clock-out'), [
        'phone' => $s['phone'], 'time_entry_id' => $entry->id,
    ])->assertOk();

    expect($entry->fresh()->end_at_utc)->not->toBeNull()
        ->and($entry->fresh()->duration_minutes)->not->toBeNull();
});

it('blocks clock APIs without a device token', function () {
    $s = tabletScenario();

    $this->postJson(qcminute('/device/lookup'), ['phone' => $s['phone']])->assertUnauthorized();
});

it('rejects a phone that does not match the work order', function () {
    $s = tabletScenario();

    $this->withHeaders(['Authorization' => "Bearer {$s['token']}", 'Accept' => 'application/json'])
        ->post(qcminute('/device/clock-in'), [
            'phone' => '(111) 000-2222', 'work_order_id' => $s['workOrder']->id, 'selfie' => UploadedFile::fake()->image('s.jpg'),
        ])->assertStatus(422);
});

it('lets a super admin create a device but forbids everyone else', function () {
    // devices.manage is deliberately super-admin only (sidebar restructure commit).
    $this->seed(RolePermissionSeeder::class);
    $property = Property::factory()->create();

    $this->actingAs(person('recruiter'))->post(main('/admin/devices'), ['name' => 'T', 'property_id' => $property->id])
        ->assertForbidden();

    $this->actingAs(person('office_manager'))->post(main('/admin/devices'), ['name' => 'T', 'property_id' => $property->id])
        ->assertForbidden();

    $this->actingAs(person('super_admin'))->post(main('/admin/devices'), ['name' => 'Front Desk', 'property_id' => $property->id])
        ->assertRedirect();

    expect(Device::query()->where('name', 'Front Desk')->exists())->toBeTrue();
});
