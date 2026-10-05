<?php

use App\Domain\People\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function main(string $path = ''): string
{
    return 'http://'.config('domains.main').$path;
}

function qcminute(string $path = ''): string
{
    return 'http://'.config('domains.qcminute').$path;
}

/** A valid public job application (marketing form) payload. */
function applicationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'email' => 'maria@example.com',
        'phone' => '214-555-0123',
        'address' => '100 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'zip' => '75201',
        'position' => 'Housekeeper',
        'desired_salary' => '16',
        'start_date' => now()->addWeek()->toDateString(),
        'dob' => '1990-05-01',
        'transportation' => '1',
        'work_at_qcp' => '0',
        'usa_citizen' => '1',
        'another_staff_agency' => '0',
        'convicted_felon' => '0',
        'full_name' => 'Jose Lopez',
        'emergency_phone' => '214-555-0199',
        'relationship' => 'Spouse',
        'full_address' => '100 Main St, Dallas TX',
        'acknowledgement' => '1',
    ], $overrides);
}

/** Create a person with the given role assigned (requires RolePermissionSeeder). */
function person(string $role): Person
{
    return tap(Person::factory()->create(['password' => Hash::make('secret')]))
        ->assignRole($role);
}
