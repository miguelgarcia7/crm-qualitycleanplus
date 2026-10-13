<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Pto\Actions\SubmitPtoRequest;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function w2Staff(string $hireDate): Person
{
    return tap(Person::factory()->create(['status' => PersonStatus::StaffActive, 'hire_date' => $hireDate]))->assignRole('w2_employee');
}

it('shows a staff member their time off on My Profile, with the next tier', function () {
    $wendy = w2Staff(now()->subMonths(9)->toDateString());
    app(SubmitPtoRequest::class)->handle($wendy, [
        'bucket' => 'vacation',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'hours' => 8,
    ]);

    $this->actingAs($wendy)
        ->get(main('/admin/settings/profile'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('self.time_off.balances', 3)
            ->where('self.time_off.balances.0.pending', 8)
            ->has('self.time_off.upcoming', 1)
            ->where('self.time_off.upcoming.0.status', 'pending')
            ->where('self.time_off.next_tier.label', '1 year')
            ->where('self.time_off.next_tier.top', true)
            ->where('can.request_change', true));
});

it('lists a change to their details while it waits on HR', function () {
    $wendy = w2Staff(now()->subMonths(9)->toDateString());
    app(StartWorkflow::class)->handle(WorkflowType::ChangePersonalInfo, $wendy, $wendy, [
        'changes' => ['phone' => '(214) 555-0100'],
        'reason' => 'New number',
    ]);

    $this->actingAs($wendy)
        ->get(main('/admin/settings/profile'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('self.requests', 1)
            ->where('self.requests.0.title', 'Change to your phone')
            ->where('self.requests.0.status', 'With HR'));
});

it('shows no time off to someone who does not earn it', function () {
    // Contractors and property managers have their profile on QC Minute.
    $this->actingAs(person('contractor'))
        ->get(qcminute('/settings/profile'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('self.time_off', null));
});

it('sends someone already signed in from the login page to their own home', function () {
    $this->actingAs(person('office_manager'))
        ->get(main('/login'))
        ->assertRedirect('/admin/dashboard');

    $this->actingAs(person('property_manager'))
        ->get(qcminute('/login'))
        ->assertRedirect('/');
});
