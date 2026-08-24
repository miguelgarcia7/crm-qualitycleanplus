<?php

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Inertia resolves page components in the browser, so a controller can happily
 * return a component name that has no file behind it and every server-side
 * assertion still passes. This walks the names Fortify is configured with and
 * checks each one against the glob the client resolver uses (resources/js/admin/app.tsx).
 */
it('points every Fortify view at a page component that exists on disk', function () {
    $source = file_get_contents(app_path('Providers/FortifyServiceProvider.php'));

    preg_match_all("/Inertia::render\('([^']+)'/", (string) $source, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $component) {
        expect(resource_path("js/admin/views/{$component}.tsx"))
            ->toBeFile("Fortify renders [{$component}] but no matching .tsx exists.");
    }
});

it('renders the forgot-password page to guests', function () {
    $this->get(main('/forgot-password'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password'));
});

it('renders the reset-password page with the token and email from the link', function () {
    $person = person('recruiter');
    $token = Password::broker()->createToken($person);

    $this->get(qcminute("/reset-password/{$token}?email=".urlencode($person->email)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/reset-password')
            ->where('token', $token)
            ->where('email', $person->email));
});

it('renders the confirm-password page to signed-in users', function () {
    $this->actingAs(person('office_manager'))
        ->get(main('/user/confirm-password'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/confirm-password'));
});

it('renders the two-factor challenge page mid-login', function () {
    $person = person('office_manager');

    $this->withSession(['login.id' => $person->id, 'login.remember' => false])
        ->get(main('/two-factor-challenge'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/two-factor-challenge'));
});

it('lets an invited user set a password from the emailed link and sign in', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/people/invite'), [
            'role' => 'property_manager',
            'name' => 'Paula Manager',
            'email' => 'paula@hotel.example.com',
            'property_ids' => [$property->id],
        ])->assertRedirect();

    $person = Person::firstWhere('email', 'paula@hotel.example.com');
    $token = Password::broker()->createToken($person);

    // Follow the link as the invitee would — a guest, not the inviting OM.
    auth()->guard('web')->logout();
    $this->flushSession();

    $this->get(qcminute("/reset-password/{$token}?email=".urlencode((string) $person->email)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/reset-password'));

    $this->post(qcminute('/reset-password'), [
        'token' => $token,
        'email' => $person->email,
        'password' => 'NewSecret123!',
        'password_confirmation' => 'NewSecret123!',
    ])->assertSessionHasNoErrors();

    $this->post(qcminute('/login'), [
        'email' => 'paula@hotel.example.com',
        'password' => 'NewSecret123!',
    ])->assertSessionHasNoErrors();

    expect(auth()->guard('web')->check())->toBeTrue();
});
