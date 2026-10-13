<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Actions\CloseWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Jobs\ProcessTemporaryAssignmentEnds;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('opens a temp child WO and leaves the home WO open', function () {
    $homeRecruiter = person('recruiter');
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive, 'primary_recruiter_id' => $homeRecruiter->id]);
    $homeWo = WorkOrder::factory()->create(['person_id' => $contractor->id, 'status' => WorkOrderStatus::Active]);
    $host = Property::factory()->create();
    $position = Position::factory()->create();

    $this->actingAs(person('admin'))
        ->post(main("/admin/work-orders/{$homeWo->id}/temporary-assignment"), [
            'new_property_id' => $host->id,
            'position_id' => $position->id,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addWeeks(2)->toDateString(),
            'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
            'reason' => 'coverage_need',
        ])->assertRedirect();

    expect($homeWo->fresh()->status)->toBe(WorkOrderStatus::Active);

    $temp = WorkOrder::query()->where('parent_wo_id', $homeWo->id)->firstOrFail();
    expect($temp->is_temporary_assignment)->toBeTrue()
        ->and($temp->source)->toBe(WorkOrderSource::TemporaryAssignment)
        ->and($temp->property_id)->toBe($host->id)
        ->and($temp->end_date)->not->toBeNull();

    // The temp doesn't change the contractor's primary recruiter (roster count).
    expect($contractor->fresh()->primary_recruiter_id)->toBe($homeRecruiter->id)
        ->and(Person::primaryContractorsOf($homeRecruiter->id)->count())->toBe(1);
});

it('auto-closes temporary assignments past their end date', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $homeWo = WorkOrder::factory()->create(['person_id' => $contractor->id, 'status' => WorkOrderStatus::Active]);
    $temp = WorkOrder::factory()->create([
        'person_id' => $contractor->id,
        'parent_wo_id' => $homeWo->id,
        'is_temporary_assignment' => true,
        'source' => WorkOrderSource::TemporaryAssignment,
        'status' => WorkOrderStatus::Active,
        'end_date' => Carbon::now()->subDays(2)->toDateString(), // over everywhere, whatever the hour
    ]);

    (new ProcessTemporaryAssignmentEnds)->handle(app(CloseWorkOrder::class));

    expect($temp->fresh()->status)->toBe(WorkOrderStatus::Closed)
        ->and($homeWo->fresh()->status)->toBe(WorkOrderStatus::Active);
});

it('keeps a temporary assignment open through its last day at the property', function () {
    // 8 PM in Chicago: already Oct 13 in UTC, still Oct 12 at the hotel.
    $this->travelTo(CarbonImmutable::parse('2026-10-12 20:00', 'America/Chicago'));

    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $chicago = Property::factory()->create(['timezone' => 'America/Chicago']);
    $temp = fn (string $end) => WorkOrder::factory()->create([
        'person_id' => $contractor->id,
        'property_id' => $chicago->id,
        'is_temporary_assignment' => true,
        'source' => WorkOrderSource::TemporaryAssignment,
        'status' => WorkOrderStatus::Active,
        'end_date' => $end,
    ]);
    $endsToday = $temp('2026-10-12');
    $endedYesterday = $temp('2026-10-11');

    (new ProcessTemporaryAssignmentEnds)->handle(app(CloseWorkOrder::class));

    expect($endsToday->fresh()->status)->toBe(WorkOrderStatus::Active)
        ->and($endedYesterday->fresh()->status)->toBe(WorkOrderStatus::Closed)
        ->and($endedYesterday->fresh()->end_date->toDateString())->toBe('2026-10-11');
});

it('rejects a temp assignment to the same property', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $homeWo = WorkOrder::factory()->create(['person_id' => $contractor->id, 'status' => WorkOrderStatus::Active]);

    $this->actingAs(person('admin'))
        ->post(main("/admin/work-orders/{$homeWo->id}/temporary-assignment"), [
            'new_property_id' => $homeWo->property_id,
            'home_property_id' => $homeWo->property_id,
            'position_id' => Position::factory()->create()->id,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addWeek()->toDateString(),
            'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
            'reason' => 'coverage_need',
        ])->assertSessionHasErrors('new_property_id');
});
