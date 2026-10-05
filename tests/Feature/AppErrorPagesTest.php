<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('shows the back office its own error page, linking home to the dashboard', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/no-such-page'))
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('home', '/admin/dashboard')
            ->where('message', null));
});

it('sends QC Minute users home to QC Minute', function () {
    $this->get(qcminute('/no-such-page'))
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('home', '/'));
});

it('answers an in-app (Inertia) visit with the error page too', function () {
    $this->actingAs(person('admin'))
        ->get(main('/admin/no-such-page'), ['X-Inertia' => 'true'])
        ->assertNotFound()
        ->assertJsonPath('component', 'error')
        ->assertJsonPath('props.status', 404);
});

it('keeps a policy’s generic 403 wording off the page', function () {
    Route::domain(config('domains.main'))->middleware('web')->get('/admin/__denied', fn () => abort(403));

    $this->actingAs(person('admin'))
        ->get(main('/admin/__denied'))
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('status', 403)->where('message', null)->where('signed_in', true));
});

it('leaves JSON clients their JSON errors', function () {
    $this->getJson(qcminute('/no-such-page'))->assertNotFound()->assertJsonStructure(['message']);
});

it('shows a calm page instead of a crash — but keeps the stack trace while debugging', function () {
    Route::domain(config('domains.main'))->middleware('web')->get('/admin/__boom', fn () => throw new RuntimeException('boom'));

    config(['app.debug' => false]);
    $this->get(main('/admin/__boom'))->assertStatus(500)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('status', 500));

    // The debug page quotes this file, so match the page payload as it is
    // HTML-encoded in an Inertia response — text this source doesn't contain.
    config(['app.debug' => true]);
    $this->get(main('/admin/__boom'))->assertStatus(500)->assertDontSee('&quot;component&quot;:&quot;error&quot;', false);
});

it('sends an expired sign-in form back for a fresh token instead of a dead end', function () {
    Route::domain(config('domains.main'))->middleware('web')->post('/admin/__expired', fn () => throw new TokenMismatchException);

    $this->from(main('/login'))->post(main('/admin/__expired'))->assertRedirect(main('/login'));
});

it('shows the maintenance card while the app is down', function () {
    $this->app->maintenanceMode()->activate([]);

    try {
        $this->get(qcminute('/'))->assertStatus(503)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('status', 503));
    } finally {
        $this->app->maintenanceMode()->deactivate();
    }
});
