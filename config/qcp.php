<?php

return [
    /*
    | The invoicing entity (Quality Cleaning Plus). Snapshotted onto every
    | invoice at generation (ADR-0006), so later edits never change past invoices.
    */
    'invoicer' => [
        'name' => env('QCP_INVOICER_NAME', 'Quality Cleaning Plus'),
        'address' => env('QCP_INVOICER_ADDRESS', ''),
        'city' => env('QCP_INVOICER_CITY', ''),
        'state' => env('QCP_INVOICER_STATE', ''),
        'zip' => env('QCP_INVOICER_ZIP', ''),
        'phone' => env('QCP_INVOICER_PHONE', ''),
        'email' => env('QCP_INVOICER_EMAIL', ''),
    ],

    /*
    | Time-bucketing rules (20-domain/time-tracking.md). Work on an attached
    | property holiday's date pays/bills at holiday_multiplier × the WO's base
    | rates, never overtime — but still advances the weekly 40h counter. One
    | config key on purpose: the legacy app hardcoded 1.5 in five places.
    */
    'time' => [
        'overtime_weekly_threshold_minutes' => 40 * 60, // hours over 40/week → overtime
        'holiday_multiplier' => 1.5,                    // holiday pay/bill = base rate × this
        'payroll_periods_ahead' => 4,                   // how many open periods to keep ready
    ],

    /*
    | Invoicing.
    */
    'invoice' => [
        'number_prefix' => 'INV',
        'payment_terms_days' => 30,
    ],
];
