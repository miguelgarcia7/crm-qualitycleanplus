<?php

return [
    /*
    | SEED DEFAULTS ONLY — not the live source. Company identity lives in the
    | `settings` table (CompanySettingsSeeder reads these on a fresh install)
    | and is edited at /admin/settings/company. Read it via CompanySettings,
    | never via this config: the DB wins once seeded.
    |
    | GenerateInvoice snapshots the block onto each invoice (ADR-0006), so
    | later edits never change past invoices.
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
        'gps_accuracy_cap_meters' => 200,               // ceiling on GpsPolicy's required accuracy (min(cap, radius/2))
    ],

    /*
    | Direct-hire eligibility. Hours a contractor must work at a property before
    | that property may hire them directly — a commercial term protecting QCP
    | against losing a placement it sourced. Properties carry their own value
    | (from their contract); this is the fallback when a property has none, and
    | the value copied onto each work order at creation.
    |
    | 2080h ≈ a full work-year. Confirm against the standard contract term.
    */
    'work_orders' => [
        'direct_hire_threshold_hours' => 2080,
    ],

    /*
    | The fee QCP charges a contractor for taking them on, deducted from pay
    | across pay periods like a uniform charge (never billed to the property).
    | Created automatically when an applicant is promoted; the per-period amount
    | is editable per contractor, and the schedule can be cancelled outright.
    */
    'hiring_fee' => [
        'amount_cents' => 250_00,
        'per_period_cents' => 20_00,
    ],

    /*
    | Invoicing.
    */
    'invoice' => [
        'number_prefix' => 'INV',
        'payment_terms_days' => 30,

        /*
         * Shown on the invoice screens and the PDF. These documents are a
         * working record; the billable one is issued elsewhere, and saying so
         * on the artefact itself is the only place a client will read it.
         * Set the notice to null to drop it everywhere.
         */
        'preview_notice' => [
            'heading' => env('QCP_INVOICE_PREVIEW_HEADING', 'This invoice is a PREVIEW ONLY.'),
            'body' => env('QCP_INVOICE_PREVIEW_BODY', 'Final invoices are issued through QuickBooks.'),
        ],
    ],

    /*
    | Who receives the marketing site's contact-form leads, keyed by
    | contact_inquiries.type. Comma-separated in .env; English and Spanish
    | forms share them. Blank = stored only, not emailed.
    */
    'marketing' => [
        'contact_recipients' => [
            'job_seeker' => array_values(array_filter(array_map('trim', explode(',', (string) env('MARKETING_JOB_SEEKERS_TO', ''))))),
            'business' => array_values(array_filter(array_map('trim', explode(',', (string) env('MARKETING_BUSINESS_TO', ''))))),
        ],
    ],
];
