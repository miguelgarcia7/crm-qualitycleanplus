<?php

namespace App\Domain\People\Support;

/**
 * The office roles a person can be given on the back office, and what each
 * one is for. A person may hold several; they get everything each allows.
 *
 * Not here on purpose: admin and super admin (set up outside the app), and
 * property manager, which belongs to the hotel and never combines with an
 * office role (it would mix pay with a hotel's view).
 */
final class StaffRoles
{
    /** role => label, in the order the dashboard switch and the editor list them. */
    public const LABELS = [
        'office_manager' => 'Office Manager',
        'front_desk' => 'Front Desk',
        'hr' => 'HR',
        'payroll' => 'Payroll',
        'recruiter' => 'Recruiter',
        'w2_employee' => 'W-2 Employee',
    ];

    /** One line on each, for the roles editor. */
    public const DOES = [
        'office_manager' => 'Runs the office: properties, work orders, timesheets, invoices.',
        'front_desk' => 'Applicants, supplies and the front desk queue.',
        'hr' => 'People, time off, info changes, terminations.',
        'payroll' => 'Pay, payouts, invoices and pay rates.',
        'recruiter' => 'Their own properties and contractors, timesheets, applicants.',
        'w2_employee' => 'Their own profile and time off only.',
    ];

    /** Roles with a dashboard of their own, in the order one is picked by default. */
    public const DASHBOARDS = ['office_manager', 'front_desk', 'hr', 'payroll', 'recruiter'];

    /** Short names for the dashboard switch. */
    public const DASHBOARD_LABELS = [
        'office_manager' => 'Office',
        'front_desk' => 'Front desk',
        'hr' => 'HR',
        'payroll' => 'Payroll',
        'recruiter' => 'Recruiting',
    ];

    /** Roles whose holder is managed outside the roles editor. */
    public const NOT_EDITABLE = ['super_admin', 'admin', 'property_manager', 'contractor'];
}
