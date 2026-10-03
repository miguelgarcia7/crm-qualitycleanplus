<?php

return [
    /*
    | Demo mode (docs/80-plan/demo-environment.md). Turns an environment into a
    | live demo of Acme Hotel: schedules `demo:simulate` (contractor punches +
    | the weekly billing rollover) and adds a noindex header to every response.
    | Set only on the Cloud `demo` environment, or locally while testing —
    | never on production, which both demo commands refuse to run against.
    */
    'enabled' => (bool) env('DEMO_MODE', false),

    /*
    | Password for every seeded demo login. Shared with the client alongside
    | the account list, so set something less guessable than the default on
    | any publicly reachable environment.
    */
    'password' => env('DEMO_PASSWORD', 'password'),
];
