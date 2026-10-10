<?php

namespace App\Domain\SystemReference\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Every permission as Admin reads it on the System reference pages: a plain
 * label, its area, and the roles that hold it — read from the database, so it
 * shows what this environment actually grants (RolePermissionSeeder writes it).
 *
 * Labels come from the permission name (`workflows.pto.approve` → "Approve
 * PTO"); the verb and noun maps cover the names in use today, and an unknown
 * part falls back to its own words rather than failing.
 */
class PermissionCatalog
{
    /** role => label, in the order roles are shown. */
    public const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'office_manager' => 'Office Manager',
        'front_desk' => 'Front Desk',
        'hr' => 'HR',
        'payroll' => 'Payroll',
        'recruiter' => 'Recruiter',
        'w2_employee' => 'W-2 Employee',
        'property_manager' => 'Property Manager',
        'contractor' => 'Contractor',
    ];

    /** First segment of the permission name => area. */
    private const AREAS = [
        'bible' => 'Property Bible',
        'job_postings' => 'Recruiting',
        'marketing' => 'Marketing site',
        'people' => 'People',
        'work_orders' => 'Work orders',
        'time_entries' => 'Time tracking',
        'field_visits' => 'Field visits',
        'devices' => 'Tablet kiosks',
        'timesheets' => 'Timesheets',
        'imports' => 'Hour imports',
        'invoices' => 'Invoices',
        'workflows' => 'Workflows & time off',
        'pto' => 'Workflows & time off',
        'termination_records' => 'Workflows & time off',
        'kb' => 'Knowledge base',
        'inventory' => 'Inventory',
        'reports' => 'Reports',
        'audit' => 'Audit & admin',
        'admin' => 'Audit & admin',
        'settings' => 'Audit & admin',
        'system' => 'Audit & admin',
    ];

    /** Prefixes that only name the area and add nothing to the label. */
    private const SILENT_PREFIXES = ['bible', 'people', 'workflows', 'inventory', 'audit', 'admin', 'settings', 'system', 'marketing'];

    private const NOUNS = [
        'pto' => 'PTO',
        'kb' => 'knowledge base',
        'more_staff' => 'staffing requests',
        'pay_increase' => 'pay increases',
        'supply_request' => 'supply requests',
        'change_personal_info' => 'personal-info changes',
        'temporary_assignment' => 'temporary assignments',
        'recruiter_transfer' => 'recruiter handovers',
        'activity_log' => 'audit log',
        'own_profile' => 'own profile',
        'time_entries' => 'punches',
        'onboarding_checklist' => 'onboarding checklist',
        'legal_hold' => 'legal hold',
        'termination' => 'terminations',
        'transfer' => 'transfers',
        'items' => 'inventory items',
    ];

    /**
     * Verb => label; a trailing ":" puts the noun in brackets after it. Names
     * in use today that read badly this way have a LABELS entry instead; this
     * is the fallback for new ones.
     */
    private const VERBS = [
        'view' => 'View',
        'edit' => 'Edit',
        'create' => 'Create',
        'delete' => 'Delete',
        'manage' => 'Manage',
        'approve' => 'Approve',
        'decline' => 'Decline',
        'initiate' => 'Start',
        'cancel' => 'Cancel',
        'cancel_own' => 'Cancel own',
        'cancel_others' => 'Cancel anyone’s',
        'export' => 'Export',
        'send' => 'Send',
        'void' => 'Void',
        'mark_paid' => 'Mark paid:',
        'download' => 'Download',
        'upload' => 'Upload',
        'commit' => 'Apply',
        'rollback' => 'Roll back',
        'promote' => 'Promote to contractor:',
        'terminate' => 'Terminate',
        'transfer' => 'Transfer',
        'fulfill' => 'Fulfill',
        'close' => 'Close',
        'close_own' => 'Close own',
        'view_own' => 'View own',
        'view_all' => 'View all',
        'view_by_property' => 'View by property:',
        'view_live' => 'View this week’s',
        'view_history' => 'View past',
        'submit_for_approval' => 'Send for approval:',
        'create_manual' => 'Add by hand:',
        'add_adjustment' => 'Add adjustments:',
        'set_external_id' => 'Set payroll ID:',
        'request_change' => 'Request changes to',
        'physical_tasks' => 'Do equipment tasks:',
        'payroll_tasks' => 'Do payroll tasks:',
        'execute' => 'Run',
        'verify' => 'Verify',
        'adjust' => 'Adjust',
        'adjust_manual' => 'Adjust by hand:',
        'receive' => 'Receive',
        'receive_direct' => 'Receive without a PO:',
        'manual_out' => 'Issue by hand:',
        'return_to_stock' => 'Return to stock:',
        'return' => 'Return',
        'deactivate' => 'Deactivate',
        'publish' => 'Publish',
        'assign' => 'Assign',
        'set' => 'Set',
        'clear' => 'Clear',
        'approve_new_item' => 'Approve new items:',
        'view_assignments' => 'View assignments:',
        'assign_different_recruiter_same_property' => 'Reassign the recruiter, same property:',
        'manual_edit' => 'Edit by hand:',
    ];

    /** Whole-name overrides where the parts don't read well. */
    private const LABELS = [
        'admin.impersonate' => 'Sign in as another user',
        'admin.users.create' => 'Create logins',
        'admin.roles.assign' => 'Assign roles',
        'settings.company.manage' => 'Edit company details',
        'devices.manage' => 'Manage tablet kiosks',
        'job_postings.manage' => 'Manage job postings',
        'marketing.inquiries.manage' => 'Answer contact inquiries',
        'people.applicants.onboarding_checklist.edit' => 'Edit the onboarding checklist',
        'people.applicants.promote' => 'Promote applicants to contractors',
        'people.contractors.set_external_id' => 'Set contractors’ payroll ID',
        'time_entries.create_manual' => 'Add punches by hand',
        'time_entries.add_adjustment' => 'Add adjustments to punches',
        'field_visits.view_by_property' => 'View field visits by property',
        'field_visits.manual_edit' => 'Edit field visits by hand',
        'timesheets.submit_for_approval' => 'Send timesheets for approval',
        'invoices.mark_paid' => 'Mark invoices paid',
        'workflows.pto.initiate' => 'Request PTO',
        'pto.balances.adjust_manual' => 'Adjust PTO balances by hand',
        'workflows.termination.physical_tasks' => 'Do termination equipment tasks',
        'workflows.termination.payroll_tasks' => 'Do termination payroll tasks',
        'workflows.transfer.assign_different_recruiter_same_property' => 'Move a contractor to another recruiter at the same property',
        'workflows.supply_request.approve_new_item' => 'Approve new items on supply requests',
        'inventory.categories.manage' => 'Manage inventory categories',
        'inventory.stock.receive_direct' => 'Receive stock without a PO',
        'inventory.stock.manual_out' => 'Issue stock by hand',
        'inventory.stock.return_to_stock' => 'Return items to stock',
        'inventory.equipment.view_assignments' => 'View equipment assignments',
        'reports.financial.view' => 'View financial reports',
        'reports.operational.view' => 'View operational reports',
        'reports.payroll.view' => 'View payroll reports',
        'system.reference.view' => 'View the system reference',
    ];

    /**
     * @return list<array{key: string, label: string, area: string, roles: list<string>}>
     */
    public function all(): array
    {
        $order = array_flip(array_keys(self::ROLE_LABELS));

        return Permission::query()->with('roles:id,name')->orderBy('id')->get()
            ->map(fn (Permission $permission): array => [
                'key' => $permission->name,
                'label' => $this->label($permission->name),
                'area' => $this->area($permission->name),
                'roles' => $permission->roles
                    ->pluck('name')
                    ->filter(fn (string $role): bool => isset($order[$role]))
                    ->sortBy(fn (string $role): int => $order[$role])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function roleCount(): int
    {
        return Role::query()->count();
    }

    public function label(string $key): string
    {
        if (isset(self::LABELS[$key])) {
            return self::LABELS[$key];
        }

        $parts = explode('.', $key);
        $verb = (string) array_pop($parts);
        $nouns = count($parts) > 1 ? array_values(array_diff($parts, self::SILENT_PREFIXES)) : $parts;
        $noun = implode(' ', array_map(fn (string $p): string => self::NOUNS[$p] ?? str_replace('_', ' ', $p), $nouns));
        $phrase = self::VERBS[$verb] ?? ucfirst(str_replace('_', ' ', $verb));

        $label = str_ends_with($phrase, ':')
            ? substr($phrase, 0, -1)." ({$noun})"
            : trim("{$phrase} {$noun}");

        return ucfirst($label);
    }

    public function area(string $key): string
    {
        return self::AREAS[strtok($key, '.')] ?? 'Other';
    }
}
