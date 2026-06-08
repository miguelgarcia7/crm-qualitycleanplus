<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
});

// Property at a known point (Phoenix) with a contractor on an active WO + open period.
function clockScenario(string $phone = '(555) 222-3333'): array
{
    $property = Property::factory()->create([
        'timezone' => 'America/Phoenix', 'latitude' => 33.4484, 'longitude' => -112.0740, 'geofence_radius_meters' => 300,
    ]);
    $position = Position::factory()->create(['name' => 'Housekeeper']);

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

    return compact('property', 'position', 'contractor', 'workOrder', 'phone');
}

/** @return array<string, mixed> */
function clockPayload(array $extra, float $lat = 33.4484, float $lng = -112.0740): array
{
    return $extra + ['lat' => $lat, 'lng' => $lng, 'accuracy' => 12, 'selfie' => UploadedFile::fake()->image('selfie.jpg')];
}

it('looks up a contractor by phone and lists their work orders', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['property']->id}/lookup"), ['phone' => $s['phone']])
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/clock-in/index')
            ->where('lookup.contractor', $s['contractor']->name)
            ->where('lookup.work_orders', fn ($wos) => collect($wos)->pluck('id')->all() === [$s['workOrder']->id]),
        );
});

it('shows a clear error for an unknown phone number', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['property']->id}/lookup"), ['phone' => '(999) 000-1111'])
        ->assertInertia(fn (AssertableInertia $page) => $page->where('lookup.error', fn ($e) => is_string($e) && $e !== ''));
});

it('clocks a contractor in inside the geofence with gps + selfie', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['property']->id}/in"), clockPayload([
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
    ]))->assertInertia(fn (AssertableInertia $page) => $page->where('result.action', 'in'));

    $entry = TimeEntry::query()->firstOrFail();
    expect($entry->clock_method)->toBe('qr')
        ->and($entry->source->value)->toBe('clock_event')
        ->and($entry->end_at_utc)->toBeNull()
        ->and($entry->clock_in_selfie_file_id)->not->toBeNull()
        ->and((float) $entry->clock_in_gps_lat)->toEqualWithDelta(33.4484, 0.0001);
});

it('blocks clock-in from outside the geofence with the distance', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['property']->id}/in"), clockPayload([
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id,
    ], lat: 34.5000, lng: -112.0740))->assertSessionHasErrors('gps');

    expect(TimeEntry::query()->count())->toBe(0);
});

it('requires gps coordinates', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['property']->id}/in"), [
        'phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id, 'selfie' => UploadedFile::fake()->image('s.jpg'),
    ])->assertSessionHasErrors(['lat', 'lng']);
});

it('rejects a phone that does not own the work order', function () {
    $s = clockScenario();

    $this->post(qcminute("/clock-in/{$s['property']->id}/in"), clockPayload([
        'phone' => '(111) 222-3333', 'work_order_id' => $s['workOrder']->id,
    ]))->assertSessionHasErrors('phone');
});

it('clocks out, setting duration and recomputing the summary', function () {
    $s = clockScenario();
    $this->post(qcminute("/clock-in/{$s['property']->id}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));
    $entry = TimeEntry::query()->firstOrFail();

    $this->post(qcminute("/clock-in/{$s['property']->id}/out"), clockPayload(['phone' => $s['phone'], 'time_entry_id' => $entry->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('result.action', 'out'));

    expect($entry->fresh()->end_at_utc)->not->toBeNull()
        ->and($entry->fresh()->duration_minutes)->not->toBeNull()
        ->and($entry->fresh()->clock_out_selfie_file_id)->not->toBeNull()
        ->and(TimeSummary::query()->where('work_order_id', $s['workOrder']->id)->exists())->toBeTrue();
});

it('blocks a second clock-in while one is open', function () {
    $s = clockScenario();
    $this->post(qcminute("/clock-in/{$s['property']->id}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));

    $this->post(qcminute("/clock-in/{$s['property']->id}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]))
        ->assertSessionHasErrors('work_order_id');

    expect(TimeEntry::query()->count())->toBe(1);
});

it('supports a lunch break as two entries the same day', function () {
    $s = clockScenario();
    $url = "/clock-in/{$s['property']->id}";

    $this->post(qcminute("{$url}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));
    $first = TimeEntry::query()->firstOrFail();
    $this->post(qcminute("{$url}/out"), clockPayload(['phone' => $s['phone'], 'time_entry_id' => $first->id]));
    $this->post(qcminute("{$url}/in"), clockPayload(['phone' => $s['phone'], 'work_order_id' => $s['workOrder']->id]));

    expect(TimeEntry::query()->count())->toBe(2)
        ->and(TimeEntry::query()->whereNull('end_at_utc')->count())->toBe(1);
});
