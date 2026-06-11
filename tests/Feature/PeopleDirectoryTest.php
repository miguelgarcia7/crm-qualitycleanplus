<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function contractor(array $attributes = []): Person
{
    return Person::factory()->create(['status' => PersonStatus::ContractorActive, ...$attributes]);
}

it('shows the directory to roles with contractor view', function (string $role) {
    contractor(['name' => 'Carla Contractor']);

    $this->actingAs(person($role))
        ->get(main('/admin/people'))
        ->assertOk()
        ->assertSee('Carla Contractor');
})->with(['admin', 'office_manager', 'front_desk', 'hr', 'payroll']);

it('blocks roles without any people view', function () {
    $this->actingAs(person('w2_employee'))
        ->get(main('/admin/people'))
        ->assertForbidden();
});

it('scopes recruiters to their own contractors', function () {
    $recruiter = person('recruiter');
    contractor(['name' => 'Mine Contractor', 'primary_recruiter_id' => $recruiter->id]);
    contractor(['name' => 'Other Contractor']);

    $this->actingAs($recruiter)
        ->get(main('/admin/people'))
        ->assertOk()
        ->assertSee('Mine Contractor')
        ->assertDontSee('Other Contractor');
});

it('only sends staff to roles with staff view', function () {
    Person::factory()->create(['name' => 'Stacy Staffer', 'status' => PersonStatus::StaffActive]);

    $this->actingAs(person('hr'))->get(main('/admin/people'))->assertSee('Stacy Staffer');
    $this->actingAs(person('payroll'))->get(main('/admin/people'))->assertDontSee('Stacy Staffer');
});

it('shows a contractor profile', function () {
    $c = contractor(['name' => 'Carla Contractor']);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/people/{$c->id}"))
        ->assertOk()
        ->assertSee('Carla Contractor');
});

it('403s a recruiter opening someone else\'s contractor', function () {
    $c = contractor();

    $this->actingAs(person('recruiter'))
        ->get(main("/admin/people/{$c->id}"))
        ->assertForbidden();
});

it('lets a recruiter open their own contractor', function () {
    $recruiter = person('recruiter');
    $c = contractor(['primary_recruiter_id' => $recruiter->id]);

    $this->actingAs($recruiter)
        ->get(main("/admin/people/{$c->id}"))
        ->assertOk();
});

it('blocks staff profiles for roles without staff view', function () {
    $staff = Person::factory()->create(['status' => PersonStatus::StaffActive]);

    $this->actingAs(person('payroll'))->get(main("/admin/people/{$staff->id}"))->assertForbidden();
    $this->actingAs(person('hr'))->get(main("/admin/people/{$staff->id}"))->assertOk();
});

it('never shows applicants in the directory or profile', function () {
    $applicant = Person::factory()->create(['name' => 'Andy Applicant', 'status' => PersonStatus::Applicant]);

    $this->actingAs(person('admin'))
        ->get(main('/admin/people'))
        ->assertOk()
        ->assertDontSee('Andy Applicant');

    $this->actingAs(person('admin'))
        ->get(main("/admin/people/{$applicant->id}"))
        ->assertForbidden();
});

it('includes terminated contractors in the directory', function () {
    contractor(['name' => 'Tina Past', 'status' => PersonStatus::Terminated]);

    $this->actingAs(person('admin'))
        ->get(main('/admin/people'))
        ->assertOk()
        ->assertSee('Tina Past');
});
