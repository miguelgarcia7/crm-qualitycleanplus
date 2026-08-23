<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds roles + permissions from the canonical matrix in
 * docs/10-architecture/permissions-matrix.md.
 *
 * super_admin receives every permission. Each other role's grants are listed
 * explicitly below (✅ in the matrix). "(own)"/"(self)"/"(role-gated)" cells
 * grant the base permission here; row-level scoping is enforced by policies.
 */
class RolePermissionSeeder extends Seeder
{
    /** All roles (ADR-0013). */
    private const ROLES = [
        'super_admin', 'admin', 'office_manager', 'front_desk', 'hr',
        'payroll', 'recruiter', 'w2_employee', 'property_manager', 'contractor',
    ];

    /**
     * permission => roles granted it (super_admin omitted — it gets all).
     *
     * @return array<string, list<string>>
     */
    private function matrix(): array
    {
        return [
            // Property Bible
            'bible.properties.view' => ['admin', 'office_manager', 'hr', 'payroll', 'recruiter', 'property_manager'],
            'bible.properties.edit' => ['admin', 'office_manager', 'payroll'],
            'bible.departments.view' => ['admin', 'office_manager', 'hr', 'payroll', 'recruiter', 'property_manager'],
            'bible.departments.edit' => ['admin', 'office_manager', 'recruiter'],
            'bible.positions.view' => ['admin', 'office_manager', 'hr', 'payroll', 'recruiter', 'property_manager'],
            'bible.positions.edit' => ['admin', 'office_manager', 'payroll'],
            'bible.rates.view' => ['admin', 'office_manager', 'payroll', 'recruiter'],
            'bible.rates.edit' => ['admin', 'office_manager', 'payroll'],
            'bible.holidays.view' => ['admin', 'office_manager', 'hr', 'payroll', 'recruiter', 'property_manager'],
            'bible.holidays.edit' => ['admin', 'office_manager', 'payroll'],
            'bible.contracts.view' => ['admin', 'payroll'],
            'bible.contracts.edit' => ['admin', 'payroll'],
            'bible.contracts.download' => ['admin', 'payroll'],

            // Recruiting (Phase 08b-ii) — advertised openings on the public job board
            'job_postings.manage' => ['admin', 'office_manager', 'hr', 'recruiter'],

            // People
            'people.applicants.view' => ['admin', 'office_manager', 'front_desk', 'hr', 'recruiter'],
            'people.applicants.edit' => ['admin', 'office_manager', 'hr', 'recruiter'],
            'people.applicants.onboarding_checklist.edit' => ['admin', 'office_manager', 'front_desk', 'hr'],
            'people.applicants.promote' => ['admin', 'office_manager', 'hr', 'recruiter'],
            'people.contractors.view' => ['admin', 'office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'property_manager'],
            'people.contractors.edit' => ['admin', 'office_manager', 'hr', 'recruiter'],
            'people.contractors.terminate' => ['admin', 'hr', 'recruiter'],
            'people.contractors.transfer' => ['admin', 'hr', 'recruiter'],
            'people.contractors.set_external_id' => ['admin', 'office_manager', 'hr', 'payroll'],
            'people.staff.view' => ['admin', 'office_manager', 'hr'],
            'people.staff.edit' => ['admin', 'office_manager', 'hr'],
            'people.own_profile.view' => ['admin', 'office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee', 'property_manager', 'contractor'],
            'people.own_profile.request_change' => ['office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee', 'contractor'],

            // Work orders
            'work_orders.view' => ['admin', 'office_manager', 'hr', 'payroll', 'recruiter', 'property_manager', 'contractor'],
            'work_orders.create' => ['admin', 'office_manager', 'recruiter'],
            'work_orders.edit' => ['admin', 'office_manager', 'recruiter'],
            'work_orders.close' => ['admin', 'office_manager', 'recruiter'],

            // Time tracking
            'time_entries.view' => ['admin', 'office_manager', 'payroll', 'recruiter', 'property_manager', 'contractor'],
            'time_entries.create_manual' => ['admin', 'office_manager', 'recruiter'],
            'time_entries.edit' => ['admin', 'office_manager', 'recruiter'],
            'time_entries.delete' => ['admin', 'office_manager', 'recruiter'],
            'time_entries.add_adjustment' => ['admin', 'office_manager', 'recruiter'],

            // Field visits (ADR-0017)
            'field_visits.create' => ['admin', 'recruiter'],
            'field_visits.close_own' => ['admin', 'recruiter'],
            'field_visits.view_own' => ['admin', 'recruiter'],
            'field_visits.view_all' => ['admin', 'office_manager', 'hr'],
            'field_visits.view_by_property' => ['admin', 'office_manager', 'hr', 'property_manager'],
            'field_visits.manual_edit' => [], // super_admin only

            // Tablet/device kiosk management (Phase 07c, ADR-0017) — super_admin only
            'devices.manage' => [],

            // Timesheets
            'timesheets.view_live' => ['admin', 'office_manager', 'payroll', 'recruiter', 'property_manager'],
            'timesheets.view_history' => ['admin', 'office_manager', 'payroll', 'recruiter', 'property_manager'],
            'timesheets.submit_for_approval' => ['admin', 'office_manager', 'recruiter'],
            'timesheets.approve' => ['admin', 'property_manager'],
            'timesheets.decline' => ['admin', 'property_manager'],
            'timesheets.export' => ['admin', 'office_manager', 'payroll', 'recruiter', 'property_manager'],

            // Imports
            'imports.upload' => ['admin', 'office_manager', 'payroll', 'recruiter'],
            'imports.commit' => ['admin', 'office_manager', 'payroll', 'recruiter'],
            'imports.rollback' => ['admin', 'office_manager', 'payroll'],

            // Invoices
            'invoices.view' => ['admin', 'office_manager', 'payroll', 'recruiter', 'property_manager'],
            'invoices.send' => ['admin', 'office_manager', 'recruiter'],
            'invoices.void' => ['admin', 'payroll'],
            'invoices.export' => ['admin', 'office_manager', 'payroll', 'recruiter', 'property_manager'],

            // Workflows
            'workflows.pto.initiate' => ['admin', 'office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee'],
            'workflows.pto.approve' => ['admin', 'hr'],
            'workflows.pto.cancel_own' => ['admin', 'office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee'],
            'workflows.pto.cancel_others' => ['admin', 'hr'],
            'pto.balances.view_all' => ['admin', 'hr', 'payroll'],
            'pto.balances.adjust_manual' => ['admin'],
            'pto.balances.view_own' => ['admin', 'office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee'],
            'workflows.termination.initiate' => ['admin', 'hr', 'recruiter'],
            'workflows.termination.physical_tasks' => ['admin', 'office_manager', 'front_desk', 'hr'],
            'workflows.termination.payroll_tasks' => ['admin', 'payroll'],
            'workflows.termination.cancel' => [], // super_admin only
            'termination_records.view' => ['admin', 'hr', 'payroll'],
            'workflows.transfer.initiate' => ['admin', 'hr', 'recruiter'],
            'workflows.transfer.assign_different_recruiter_same_property' => ['admin'],
            'workflows.transfer.cancel' => [], // super_admin only
            'workflows.temporary_assignment.initiate' => ['admin', 'hr', 'recruiter'],
            'workflows.temporary_assignment.cancel' => [], // super_admin only
            'workflows.supply_request.initiate' => ['admin', 'office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee'],
            'workflows.supply_request.fulfill' => ['admin', 'office_manager', 'front_desk'],
            'workflows.supply_request.approve_new_item' => ['admin'],
            'workflows.more_staff.initiate' => ['admin', 'hr', 'recruiter', 'property_manager'],
            'workflows.more_staff.fulfill' => ['admin', 'recruiter'],
            'workflows.more_staff.decline' => ['admin', 'recruiter'],
            'workflows.more_staff.cancel_own' => ['admin', 'recruiter', 'property_manager'],
            'workflows.more_staff.cancel_others' => [], // super_admin only
            'workflows.pay_increase.initiate' => ['admin', 'hr', 'recruiter', 'property_manager'],
            'workflows.pay_increase.approve' => ['admin', 'recruiter'],
            'workflows.pay_increase.cancel_own' => ['admin', 'recruiter', 'property_manager'],
            'workflows.pay_increase.cancel_others' => [], // super_admin only
            'workflows.recruiter_transfer.execute' => ['admin', 'office_manager'],
            'workflows.change_personal_info.verify' => ['admin', 'hr'],

            // Knowledge base
            'kb.articles.view' => ['admin', 'office_manager', 'front_desk', 'hr', 'payroll', 'recruiter', 'w2_employee', 'contractor'],
            'kb.articles.create' => ['admin', 'office_manager', 'hr'],
            'kb.articles.edit' => ['admin', 'office_manager', 'hr'],
            'kb.articles.publish' => ['admin', 'office_manager'],
            'kb.articles.delete' => ['admin', 'office_manager'],
            'kb.categories.manage' => ['admin', 'office_manager'],
            'kb.feedback.manage' => ['admin', 'office_manager'],

            // Inventory (ADR-0012)
            'inventory.items.view' => ['admin', 'office_manager', 'front_desk'],
            'inventory.items.create' => ['admin', 'office_manager'],
            'inventory.items.edit' => ['admin', 'office_manager'],
            'inventory.items.deactivate' => ['admin', 'office_manager'],
            'inventory.categories.manage' => ['admin', 'office_manager'],
            'inventory.stock.receive_direct' => ['admin', 'office_manager', 'front_desk'],
            'inventory.stock.adjust' => ['admin', 'office_manager', 'front_desk'],
            'inventory.stock.manual_out' => ['admin', 'office_manager', 'front_desk'],
            'inventory.stock.return_to_stock' => ['admin', 'office_manager', 'front_desk'],
            'inventory.purchase_orders.view' => ['admin', 'office_manager', 'front_desk'],
            'inventory.purchase_orders.create' => ['admin', 'office_manager', 'front_desk'],
            'inventory.purchase_orders.receive' => ['admin', 'office_manager', 'front_desk'],
            'inventory.equipment.return' => ['admin', 'office_manager', 'front_desk'],
            'inventory.equipment.view_assignments' => ['admin', 'office_manager', 'front_desk'],

            // Reports
            'reports.financial.view' => ['admin', 'office_manager', 'payroll'],
            'reports.operational.view' => ['admin', 'office_manager', 'hr', 'recruiter', 'property_manager'],
            'reports.payroll.view' => ['admin', 'payroll'],

            // Audit & admin
            'audit.activity_log.view' => ['admin', 'office_manager'],
            'audit.legal_hold.set' => ['admin'],
            'audit.legal_hold.clear' => ['admin'],
            'admin.impersonate' => ['admin'],
            'admin.users.create' => ['admin', 'office_manager', 'hr'],
            'admin.roles.assign' => ['admin'],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [];
        foreach (self::ROLES as $name) {
            $roles[$name] = Role::findOrCreate($name, 'web');
        }

        $matrix = $this->matrix();

        foreach (array_keys($matrix) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ($matrix as $permission => $grantedRoles) {
            foreach ($grantedRoles as $roleName) {
                $roles[$roleName]->givePermissionTo($permission);
            }
        }

        // super_admin holds every permission (and stays full forever — ADR-0013).
        $roles['super_admin']->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
