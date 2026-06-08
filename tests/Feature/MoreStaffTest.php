<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Notifications\WorkflowNotice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{property: Property, position: Position, pm: Person, recruiter: Person} */
function moreStaffScenario(): array
{
    $property = Property::factory()->create();
    $position = Position::factory()->create();
    $property->positionRates()->create([
        'position_id' => $position->id, 'effective_date' => now()->subMonth()->toDateString(),
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500, 'is_active' => true,
    ]);

    $recruiter = person('recruiter');
    $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    $pm = person('property_manager');
    $property->assignments()->create(['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

    return compact('property', 'position', 'pm', 'recruiter');
}

function submitStaffRequest(array $s, int $quantity = 2): MoreStaffRequest
{
    test()->actingAs($s['pm'])->post(qcminute('/staffing-requests'), [
        'property_id' => $s['property']->id,
        'position_id' => $s['position']->id,
        'quantity' => $quantity,
        'by_date' => Carbon::now()->addWeeks(2)->toDateString(),
        'urgency' => 'high',
        'reason' => 'Banquet season',
    ])->assertRedirect();

    return MoreStaffRequest::query()->latest('id')->firstOrFail();
}

function placeContractor(array $s, MoreStaffRequest $request): void
{
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);

    test()->actingAs(person('admin'))->post(main('/admin/work-orders'), [
        'person_id' => $contractor->id,
        'property_id' => $s['property']->id,
        'position_id' => $s['position']->id,
        'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
        'start_date' => Carbon::now()->toDateString(),
        'more_staff_request_id' => $request->id,
    ])->assertRedirect();
}

it('lets a PM submit a staffing request and notifies the recruiter', function () {
    $s = moreStaffScenario();
    $request = submitStaffRequest($s);

    expect($request->status)->toBe(MoreStaffStatus::Submitted)
        ->and($request->assigned_recruiter_id)->toBe($s['recruiter']->id)
        ->and($request->workflow->type)->toBe(WorkflowType::MoreStaff->value)
        ->and($request->workflow->currentStep()->step_key)->toBe('fulfill_staffing');

    Notification::assertSentTo($s['recruiter'], WorkflowNotice::class);
});

it('bumps fulfillment and moves to in_progress when a WO is linked', function () {
    $s = moreStaffScenario();
    $request = submitStaffRequest($s, quantity: 2);

    placeContractor($s, $request);

    expect($request->fresh()->quantity_fulfilled)->toBe(1)
        ->and($request->fresh()->status)->toBe(MoreStaffStatus::InProgress)
        ->and($request->workflow->fresh()->status)->toBe(WorkflowStatus::InProgress);

    Notification::assertSentTo($s['pm'], WorkflowNotice::class);
});

it('marks fulfilled and completes the workflow when the quantity is met', function () {
    $s = moreStaffScenario();
    $request = submitStaffRequest($s, quantity: 1);

    placeContractor($s, $request);

    expect($request->fresh()->status)->toBe(MoreStaffStatus::Fulfilled)
        ->and($request->fresh()->fulfilled_at)->not->toBeNull()
        ->and($request->fresh()->quantity_fulfilled)->toBe(1)
        ->and($request->workflow->fresh()->status)->toBe(WorkflowStatus::Completed);
});

it('lets the recruiter decline with a reason and notifies the PM', function () {
    $s = moreStaffScenario();
    $request = submitStaffRequest($s);

    $this->actingAs($s['recruiter'])
        ->post(main("/admin/staffing-requests/{$request->id}/decline"), ['reason' => 'No candidates available'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(MoreStaffStatus::Declined)
        ->and($request->fresh()->decline_reason)->toBe('No candidates available')
        ->and($request->workflow->fresh()->status)->toBe(WorkflowStatus::Rejected);

    Notification::assertSentTo($s['pm'], WorkflowNotice::class);
});

it('lets the PM cancel their own open request', function () {
    $s = moreStaffScenario();
    $request = submitStaffRequest($s);

    $this->actingAs($s['pm'])
        ->post(qcminute("/staffing-requests/{$request->id}/cancel"), ['reason' => 'Filled internally'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(MoreStaffStatus::Cancelled)
        ->and($request->workflow->fresh()->status)->toBe(WorkflowStatus::Cancelled);
});

it('forbids a recruiter not assigned to the property from declining', function () {
    $s = moreStaffScenario();
    $request = submitStaffRequest($s);

    $this->actingAs(person('recruiter'))
        ->post(main("/admin/staffing-requests/{$request->id}/decline"), ['reason' => 'nope'])
        ->assertForbidden();
});

it('rejects linking a WO whose property does not match the request', function () {
    $s = moreStaffScenario();
    $request = submitStaffRequest($s);
    $otherProperty = Property::factory()->create();
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);

    $this->actingAs(person('admin'))->post(main('/admin/work-orders'), [
        'person_id' => $contractor->id,
        'property_id' => $otherProperty->id,
        'position_id' => $s['position']->id,
        'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
        'start_date' => Carbon::now()->toDateString(),
        'more_staff_request_id' => $request->id,
    ])->assertSessionHasErrors('more_staff_request_id');

    expect($request->fresh()->quantity_fulfilled)->toBe(0);
});

it('blocks a contractor from submitting a staffing request', function () {
    $s = moreStaffScenario();

    $this->actingAs(person('contractor'))->post(qcminute('/staffing-requests'), [
        'property_id' => $s['property']->id, 'position_id' => $s['position']->id,
        'quantity' => 1, 'by_date' => Carbon::now()->addWeek()->toDateString(),
        'urgency' => 'normal', 'reason' => 'x',
    ])->assertForbidden();
});
