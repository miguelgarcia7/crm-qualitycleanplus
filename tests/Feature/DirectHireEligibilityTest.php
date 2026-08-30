<?php

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Actions\NotifyDirectHireEligible;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use App\Notifications\DirectHireEligible;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Record worked minutes against a work order for one week. */
function workedMinutes(WorkOrder $workOrder, int $regular, int $training = 0): void
{
    $period = PayrollPeriod::factory()->create(['property_id' => $workOrder->property_id]);

    TimeSummary::factory()->create([
        'work_order_id' => $workOrder->id,
        'person_id' => $workOrder->person_id,
        'property_id' => $workOrder->property_id,
        'payroll_period_id' => $period->id,
        'regular_minutes' => $regular,
        'overtime_minutes' => 0,
        'holiday_minutes' => 0,
        'training_minutes' => $training,
    ]);
}

it('takes the threshold from the property when one is contracted', function () {
    $property = Property::factory()->create(['direct_hire_threshold_minutes' => 500 * 60]);

    $workOrder = WorkOrder::factory()->create(['property_id' => $property->id]);

    expect($workOrder->direct_hire_threshold_minutes)->toBe(500 * 60);
});

it('falls back to the system default when the property has none', function () {
    config()->set('qcp.work_orders.direct_hire_threshold_hours', 1000);
    $property = Property::factory()->create(['direct_hire_threshold_minutes' => null]);

    expect(WorkOrder::factory()->create(['property_id' => $property->id])->direct_hire_threshold_minutes)
        ->toBe(1000 * 60);
});

it('lets a work order override the property value', function () {
    $property = Property::factory()->create(['direct_hire_threshold_minutes' => 500 * 60]);

    $workOrder = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'direct_hire_threshold_minutes' => 90 * 60,
    ]);

    expect($workOrder->direct_hire_threshold_minutes)->toBe(90 * 60);
});

it('does not move the goalposts on placements already running', function () {
    $property = Property::factory()->create(['direct_hire_threshold_minutes' => 500 * 60]);
    $workOrder = WorkOrder::factory()->create(['property_id' => $property->id]);

    // Renegotiated contract — existing placements keep the term they started on.
    $property->update(['direct_hire_threshold_minutes' => 2000 * 60]);

    expect($workOrder->fresh()->direct_hire_threshold_minutes)->toBe(500 * 60);
});

it('counts worked hours toward the threshold but never training', function () {
    $workOrder = WorkOrder::factory()->create(['direct_hire_threshold_minutes' => 100 * 60]);

    // 40h worked, 10h training — training is paid by QCP and never billed, so
    // it is not time worked for the property.
    workedMinutes($workOrder, regular: 40 * 60, training: 10 * 60);

    $progress = app(DirectHireProgress::class)->for($workOrder);

    expect($progress['worked_hours'])->toBe(40.0)
        ->and($progress['remaining_hours'])->toBe(60.0)
        ->and($progress['percent'])->toBe(40)
        ->and($progress['eligible'])->toBeFalse();
});

it('reports eligible once the threshold is reached', function () {
    $workOrder = WorkOrder::factory()->create(['direct_hire_threshold_minutes' => 100 * 60]);
    workedMinutes($workOrder, regular: 100 * 60);

    expect(app(DirectHireProgress::class)->for($workOrder)['eligible'])->toBeTrue();
});

it('treats a zero threshold as unrestricted rather than instantly eligible', function () {
    $workOrder = WorkOrder::factory()->create(['direct_hire_threshold_minutes' => 0]);

    $progress = app(DirectHireProgress::class)->for($workOrder);

    expect($progress['unrestricted'])->toBeTrue()
        ->and($progress['eligible'])->toBeTrue();
});

it('tells the recruiters once when a placement crosses the threshold', function () {
    Notification::fake();

    $property = Property::factory()->create();
    $recruiter = person('recruiter');
    $property->assignments()->create([
        'person_id' => $recruiter->id,
        'role' => PropertyAssignmentRole::Recruiter->value,
    ]);

    $workOrder = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'direct_hire_threshold_minutes' => 100 * 60,
    ]);
    workedMinutes($workOrder, regular: 120 * 60);

    $action = app(NotifyDirectHireEligible::class);
    $action->handle($workOrder->fresh());
    $action->handle($workOrder->fresh()); // a later clock-out must not re-notify

    Notification::assertSentToTimes($recruiter, DirectHireEligible::class, 1);
    expect($workOrder->fresh()->direct_hire_notified_at)->not->toBeNull();
});

it('stays quiet while a placement is short of the threshold', function () {
    Notification::fake();

    $workOrder = WorkOrder::factory()->create(['direct_hire_threshold_minutes' => 100 * 60]);
    workedMinutes($workOrder, regular: 30 * 60);

    app(NotifyDirectHireEligible::class)->handle($workOrder);

    Notification::assertNothingSent();
    expect($workOrder->fresh()->direct_hire_notified_at)->toBeNull();
});

it('shows a property manager only their own properties, with progress', function () {
    $mine = Property::factory()->create();
    $pm = person('property_manager');
    $mine->assignments()->create(['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

    $ours = WorkOrder::factory()->create(['property_id' => $mine->id, 'direct_hire_threshold_minutes' => 100 * 60]);
    workedMinutes($ours, regular: 25 * 60);
    WorkOrder::factory()->create(['property_id' => Property::factory()->create()->id]);

    $this->actingAs($pm)
        ->get(qcminute('/contractors'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('minute/contractors/index')
            ->where('contractors', function ($rows) use ($ours) {
                $rows = collect($rows);

                return $rows->count() === 1
                    && $rows->first()['id'] === $ours->id
                    && $rows->first()['direct_hire']['percent'] === 25;
            }));
});

it('keeps a contractor off the roster', function () {
    $this->actingAs(person('contractor'))
        ->get(qcminute('/contractors'))
        ->assertForbidden();
});

/** Recruiters can be Person factories without a primary recruiter set. */
it('notifies the primary recruiter even with no property assignment', function () {
    Notification::fake();

    $recruiter = person('recruiter');
    $contractor = Person::factory()->create(['primary_recruiter_id' => $recruiter->id]);

    $workOrder = WorkOrder::factory()->create([
        'person_id' => $contractor->id,
        'direct_hire_threshold_minutes' => 10 * 60,
    ]);
    workedMinutes($workOrder, regular: 20 * 60);

    app(NotifyDirectHireEligible::class)->handle($workOrder);

    Notification::assertSentTo($recruiter, DirectHireEligible::class);
});
