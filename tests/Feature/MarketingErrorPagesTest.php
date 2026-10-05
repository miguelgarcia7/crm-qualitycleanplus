<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

it('shows the site’s own page-not-found, in the visitor’s language', function (string $path, string $lang, string $title) {
    $this->get(main($path))
        ->assertNotFound()
        ->assertSee('<html class="no-js" lang="'.$lang.'">', false)
        ->assertSee('<h1>'.$title.'</h1>', false)
        ->assertSee('main-navigation', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
})->with([
    'English' => ['/no-such-page', 'en', 'Page not found'],
    'Spanish' => ['/es/no-existe', 'es', 'Página no encontrada'],
    'unknown posting' => ['/application/no-such-posting', 'en', 'Page not found'],
]);

it('leaves the back office, sign-in and API errors alone', function () {
    $this->get(main('/admin/no-such-page'))->assertNotFound()->assertDontSee('main-navigation', false);
    $this->getJson(main('/no-such-page'))->assertNotFound()->assertJsonStructure(['message']);
    $this->get(qcminute('/no-such-page'))->assertDontSee('main-navigation', false);
});

it('explains a rate limit in the site’s page, keeping Retry-After', function () {
    foreach (range(1, 10) as $ignored) {
        $this->post(main('/es/contactenos/solicitantes-de-empleo'), ['contact_first_name' => 'A', 'contact_last_name' => 'B', 'contact_email' => 'a@example.com']);
    }

    $this->post(main('/es/contactenos/solicitantes-de-empleo'), [])
        ->assertTooManyRequests()
        ->assertSee('<h1>Demasiados intentos</h1>', false)
        ->assertHeader('Retry-After');
});

it('shows a calm page instead of a crash — but keeps the stack trace while debugging', function () {
    Route::domain(config('domains.main'))->middleware('web')->get('/__boom', fn () => throw new RuntimeException('boom'));

    config(['app.debug' => false]);
    $this->get(main('/__boom'))->assertStatus(500)->assertSee('<h1>Something went wrong</h1>', false)->assertDontSee('RuntimeException');

    // The debug page quotes this file's source, so look for markup only the
    // site layout produces.
    config(['app.debug' => true]);
    $this->get(main('/__boom'))->assertStatus(500)->assertDontSee('<html class="no-js" lang="en">', false);
});

it('sends an expired form back with the answers still filled in', function () {
    Route::domain(config('domains.main'))->middleware('web')->post('/__expired', fn () => throw new TokenMismatchException);

    $this->from(main('/es/solicitud'))
        ->post(main('/__expired'), ['first_name' => 'Maria', '_token' => 'stale', 'g-recaptcha-response' => 'old'])
        ->assertRedirect(main('/es/solicitud'))
        ->assertSessionHasErrors('form')
        ->assertSessionHasInput('first_name', 'Maria')
        ->assertSessionMissing('_old_input._token')
        ->assertSessionMissing('_old_input.g-recaptcha-response');
});

it('shows a maintenance page while the site is down', function () {
    $this->app->maintenanceMode()->activate([]);

    try {
        $this->get(main('/es'))->assertStatus(503)->assertSee('<h1>Volvemos enseguida</h1>', false);
    } finally {
        $this->app->maintenanceMode()->deactivate();
    }
});
