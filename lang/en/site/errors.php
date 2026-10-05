<?php

// Marketing site — error pages (App\Domain\Marketing\Support\SiteErrorPage).
return [
    'help' => 'Need a hand? Call us at :phone.',
    'home' => 'Go to the home page',
    'jobs' => 'See job openings',
    'contact' => 'Contact us',
    'back' => 'Go back',

    'expired_form' => 'This form was open for a while, so we couldn’t accept it. Your answers are still here — please send it again.',

    403 => [
        'title' => 'Not allowed',
        'message' => 'You don’t have access to this page.',
    ],
    404 => [
        'title' => 'Page not found',
        'message' => 'We couldn’t find the page you were looking for. It may have moved, or the link may be out of date.',
    ],
    419 => [
        'title' => 'Page expired',
        'message' => 'This page was open for a while. Please go back, refresh and try again.',
    ],
    429 => [
        'title' => 'Too many attempts',
        'message' => 'We received several requests from you in a short time. Please wait a minute and try again.',
    ],
    500 => [
        'title' => 'Something went wrong',
        'message' => 'Something went wrong on our side. Please try again in a few minutes.',
    ],
    503 => [
        'title' => 'We’ll be right back',
        'message' => 'We’re making some improvements to the site. Please check back in a few minutes.',
    ],
];
