<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\WorkflowNotice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{wo: WorkOrder, property: Property, contractor: Person, pm: Person, period: PayrollPeriod} */
function payIncreaseScenario(): array
{
    $property = Property::factory()->create();
    $position = Position::factory()->create();
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $wo = WorkOrder::factory()->create([
        'person_id' => $contractor->id, 'property_id' => $property->id, 'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'status' => WorkOrderStatus::Active,
    ]);

    $pm = person('property_manager');
    $property->assignments()->create(['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value]);

    $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id, 'week_start' => $monday->toDateString(),
        'week_end' => $monday->copy()->addDays(6)->toDateString(), 'status' => PayrollPeriodStatus::Open,
    ]);

    return compact('wo', 'property', 'contractor', 'pm', 'period');
}

it('shows the PM their bill rate for each contractor, and never the pay rate', function () {
    $s = payIncreaseScenario();

    $this->actingAs($s['pm'])
        ->get(qcminute('/pay-increases'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('minute/pay-increases/index')
            ->where('workOrders.0.id', $s['wo']->id)
            ->where('workOrders.0.bill_rate', 3000)
            ->missing('workOrders.0.pay_rate')
            ->missing('workOrders.0.ot_pay_rate'));
});

it('lets a PM submit and a recruiter approve a pay increase', function () {
    Notification::fake();
    $s = payIncreaseScenario();

    $this->actingAs($s['pm'])
        ->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'great work'])
        ->assertRedirect();

    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();
    expect($workflow->status)->toBe(WorkflowStatus::InProgress)
        ->and($workflow->currentStep()->step_key)->toBe('approve_pay_increase');

    $this->actingAs(person('admin'))
        ->post(main("/admin/pay-increases/{$workflow->id}/approve"), [
            'pay_rate' => 21, 'bill_rate' => 31, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 46.5,
            'effective_period_id' => $s['period']->id,
        ])->assertRedirect();

    expect($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Closed)
        ->and($workflow->fresh()->status)->toBe(WorkflowStatus::Completed);

    $new = WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->firstOrFail();
    expect($new->source)->toBe(WorkOrderSource::PayIncrease)
        ->and($new->bill_rate)->toBe(3100)
        ->and($new->pay_rate)->toBe(2100);

    Notification::assertSentTo($s['contractor'], WorkflowNotice::class);
    Notification::assertSentTo($s['pm'], WorkflowNotice::class);
});

it('enforces the PM bill-rate floor on approval', function () {
    $s = payIncreaseScenario();
    $this->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'x']);
    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();

    // current bill $30 + $1 PM increase = $31 floor; approving at $30.50 must fail.
    $this->actingAs(person('admin'))
        ->post(main("/admin/pay-increases/{$workflow->id}/approve"), [
            'pay_rate' => 21, 'bill_rate' => 30.5, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 45.75,
            'effective_period_id' => $s['period']->id,
        ])->assertSessionHasErrors('bill_rate');

    expect($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Active)
        ->and(WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->exists())->toBeFalse();
});

it('declining a pay increase rejects the workflow and creates no WO', function () {
    Notification::fake();
    $s = payIncreaseScenario();
    $this->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'x']);
    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();

    $this->actingAs(person('admin'))
        ->post(main("/admin/pay-increases/{$workflow->id}/decline"), ['reason' => 'budget'])
        ->assertRedirect();

    expect($workflow->fresh()->status)->toBe(WorkflowStatus::Rejected)
        ->and(WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->exists())->toBeFalse();
});

it('applies a recruiter-initiated pay increase immediately', function () {
    $s = payIncreaseScenario();

    $this->actingAs(person('admin'))
        ->post(main('/admin/pay-increases'), [
            'work_order_id' => $s['wo']->id,
            'pay_rate' => 22, 'bill_rate' => 33, 'ot_pay_rate' => 33, 'ot_bill_rate' => 49.5,
            'effective_period_id' => $s['period']->id, 'reason' => 'merit',
        ])->assertRedirect();

    $new = WorkOrder::query()->where('parent_wo_id', $s['wo']->id)->firstOrFail();
    expect($new->source)->toBe(WorkOrderSource::PayIncrease)
        ->and($new->pay_rate)->toBe(2200)
        ->and($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Closed);
});

it('forbids a role without approve permission', function () {
    $s = payIncreaseScenario();
    $this->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'x']);
    $workflow = Workflow::query()->where('type', WorkflowType::PayIncrease->value)->firstOrFail();

    $this->actingAs(person('front_desk'))
        ->post(main("/admin/pay-increases/{$workflow->id}/approve"), [
            'pay_rate' => 21, 'bill_rate' => 31, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 46.5,
            'effective_period_id' => $s['period']->id,
        ])->assertForbidden();
});

it('shows a recruiter only their own properties\' requests', function () {
    $s = payIncreaseScenario();
    $this->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'x']);

    $mine = person('recruiter');
    $s['property']->assignments()->create(['person_id' => $mine->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
    $other = Person::factory()->create();
    $other->assignRole('recruiter');

    $this->actingAs($mine)->get(main('/admin/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('pending', 1));
    $this->actingAs($other)->get(main('/admin/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('pending', 0));
});

it('offers this week and the coming weeks, defaulting to next week', function () {
    $s = payIncreaseScenario();
    $next = PayrollPeriod::factory()->create([
        'property_id' => $s['property']->id,
        'week_start' => $s['period']->week_start->copy()->addWeek()->toDateString(),
        'week_end' => $s['period']->week_end->copy()->addWeek()->toDateString(),
        'status' => PayrollPeriodStatus::Open,
    ]);

    $this->actingAs(person('admin'))->get(main('/admin/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('workOrders.0.default_period_id', $next->id)
            ->where('workOrders.0.periods.0.id', $s['period']->id)
            ->where('workOrders.0.periods.0.label', fn (string $label) => str_starts_with($label, 'This week — '))
            ->where('workOrders.0.periods.1.label', fn (string $label) => str_starts_with($label, 'Next week — ')));
});

it('refuses a "raise" that lowers or keeps the rates', function (float $pay, float $bill) {
    $s = payIncreaseScenario();

    $this->actingAs(person('admin'))
        ->post(main('/admin/pay-increases'), [
            'work_order_id' => $s['wo']->id,
            'pay_rate' => $pay, 'bill_rate' => $bill, 'ot_pay_rate' => $pay * 1.5, 'ot_bill_rate' => $bill * 1.5,
            'effective_period_id' => $s['period']->id,
        ])->assertSessionHasErrors('pay_rate');

    expect($s['wo']->fresh()->status)->toBe(WorkOrderStatus::Active);
})->with([
    'unchanged' => [20, 30],
    'pay lowered' => [19, 31],
]);

it('refuses a start week from another property', function () {
    $s = payIncreaseScenario();
    $elsewhere = PayrollPeriod::factory()->create([
        'property_id' => Property::factory()->create()->id,
        'week_start' => $s['period']->week_start, 'week_end' => $s['period']->week_end,
        'status' => PayrollPeriodStatus::Open,
    ]);

    $this->actingAs(person('admin'))
        ->post(main('/admin/pay-increases'), [
            'work_order_id' => $s['wo']->id,
            'pay_rate' => 21, 'bill_rate' => 31, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 46.5,
            'effective_period_id' => $elsewhere->id,
        ])->assertSessionHasErrors('effective_period_id');
});

/** The PM's request for $1.00/hr on the scenario's work order. */
function pmRequest(array $s): Workflow
{
    test()->actingAs($s['pm'])->post(qcminute('/pay-increases'), ['work_order_id' => $s['wo']->id, 'increase_amount' => 1, 'reason' => 'great work']);

    return Workflow::query()->where('type', WorkflowType::PayIncrease->value)->latest('id')->firstOrFail();
}

it('shows the PM who each request is for, the rate asked for, and its status', function () {
    $s = payIncreaseScenario();
    pmRequest($s);

    $this->actingAs($s['pm'])->get(qcminute('/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('requests.0.contractor', $s['contractor']->name)
            ->where('requests.0.current_bill_rate', 3000)
            ->where('requests.0.increase', 100)
            ->where('requests.0.status', 'in_progress')
            ->where('requests.0.can_change', true)
            ->missing('requests.0.pay_rate'));
});

it('lets the PM change a waiting request, but not once a recruiter has acted', function () {
    $s = payIncreaseScenario();
    $workflow = pmRequest($s);

    $this->actingAs($s['pm'])
        ->patch(qcminute("/pay-increases/{$workflow->id}"), ['increase_amount' => 1.5, 'reason' => 'covers weekends too'])
        ->assertRedirect();
    expect($workflow->fresh()->data)->toMatchArray(['pm_requested_increase_cents' => 150, 'reason' => 'covers weekends too']);

    $this->actingAs(person('admin'))->post(main("/admin/pay-increases/{$workflow->id}/decline"), ['reason' => 'budget']);

    $this->actingAs($s['pm'])
        ->patch(qcminute("/pay-increases/{$workflow->id}"), ['increase_amount' => 2, 'reason' => 'x'])
        ->assertSessionHasErrors('request');
});

it('lets the PM cancel a waiting request, which leaves the recruiters\' queue and tasks', function () {
    $s = payIncreaseScenario();
    $workflow = pmRequest($s);
    $recruiter = person('recruiter');
    $s['property']->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
    expect(WorkflowStep::query()->openForPerson($recruiter)->count())->toBe(1);

    $this->actingAs($s['pm'])->post(qcminute("/pay-increases/{$workflow->id}/cancel"))->assertRedirect();

    expect($workflow->fresh()->status)->toBe(WorkflowStatus::Cancelled)
        ->and(WorkflowStep::query()->openForPerson($recruiter)->count())->toBe(0);
    $this->actingAs($recruiter)->get(main('/admin/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('pending', 0));
});

it('keeps other property managers away from a request', function () {
    $s = payIncreaseScenario();
    $workflow = pmRequest($s);
    $otherPm = Person::factory()->create();
    $otherPm->assignRole('property_manager');

    $this->actingAs($otherPm)->post(qcminute("/pay-increases/{$workflow->id}/cancel"))->assertForbidden();
    $this->actingAs($otherPm)->patch(qcminute("/pay-increases/{$workflow->id}"), ['increase_amount' => 5, 'reason' => 'x'])->assertForbidden();
    expect($workflow->fresh()->status)->toBe(WorkflowStatus::InProgress);
});

it('keeps decided requests in History with who decided and why', function () {
    $s = payIncreaseScenario();
    $workflow = pmRequest($s);
    $recruiter = person('recruiter');
    $s['property']->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    $this->actingAs($recruiter)->post(main("/admin/pay-increases/{$workflow->id}/decline"), ['reason' => 'Budget is set until January']);

    $this->actingAs($recruiter)->get(main('/admin/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pending', 0)
            ->has('history', 1)
            ->where('history.0.contractor', $s['contractor']->name)
            ->where('history.0.outcome', 'declined')
            ->where('history.0.decided_by', $recruiter->name)
            ->where('history.0.note', 'Budget is set until January')
            ->where('history.0.pm_requested_increase', 100)
            ->where('history.0.to', null));

    // A recruiter on other properties doesn't see it.
    $other = Person::factory()->create();
    $other->assignRole('recruiter');
    $this->actingAs($other)->get(main('/admin/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('history', 0));
});

it('records approved raises in History with the before and after rates', function () {
    $s = payIncreaseScenario();
    $workflow = pmRequest($s);

    $this->actingAs(person('admin'))->post(main("/admin/pay-increases/{$workflow->id}/approve"), [
        'pay_rate' => 21, 'bill_rate' => 31, 'ot_pay_rate' => 31.5, 'ot_bill_rate' => 46.5,
        'effective_period_id' => $s['period']->id,
    ]);

    $this->actingAs(person('admin'))->get(main('/admin/pay-increases'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('history.0.outcome', 'approved')
            ->where('history.0.from', ['pay_rate' => 2000, 'bill_rate' => 3000])
            ->where('history.0.to', ['pay_rate' => 2100, 'bill_rate' => 3100])
            ->where('history.0.effective', $s['period']->week_start->format('M j, Y')));
});

it('writes each step of a request to the contractor\'s history', function () {
    $s = payIncreaseScenario();
    $workflow = pmRequest($s);
    $this->actingAs(person('admin'))->post(main("/admin/pay-increases/{$workflow->id}/decline"), ['reason' => 'budget']);

    $log = Activity::query()->where('subject_type', $s['contractor']->getMorphClass())->where('subject_id', $s['contractor']->id)
        ->where('event', 'pay_increase')->oldest('id')->pluck('description')->all();

    expect($log)->toHaveCount(2)
        ->and($log[0])->toStartWith('Pay increase requested by')
        ->and($log[1])->toBe('Pay increase declined: budget');
});

it('sends the approval task only to the property\'s recruiters, with a Review link', function () {
    $s = payIncreaseScenario();
    $workflow = pmRequest($s);
    $step = $workflow->steps()->where('step_key', 'approve_pay_increase')->firstOrFail();

    $mine = person('recruiter');
    $s['property']->assignments()->create(['person_id' => $mine->id, 'role' => PropertyAssignmentRole::Recruiter->value]);
    $other = Person::factory()->create();
    $other->assignRole('recruiter');

    $this->actingAs($mine)->get(main('/admin/tasks'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.review_url', "/admin/pay-increases?review={$workflow->id}")
            ->where('tasks.0.summary', fn (string $summary) => str_contains($summary, $s['contractor']->name) && str_ends_with($summary, '+$1.00/hr')));

    $this->actingAs($other)->get(main('/admin/tasks'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks', 0));
    $this->actingAs($other)->post(main("/admin/workflow-steps/{$step->id}/reject"), ['reason' => 'not mine'])->assertForbidden();

    expect($workflow->fresh()->status)->toBe(WorkflowStatus::InProgress);
});
