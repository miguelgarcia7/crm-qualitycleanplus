import { type MenuItemType } from '@/types'

/*
| Back office sidebar menu. Items with a `permission` only render for users who
| have it (gated in Sidenav/AppMenu against the shared auth.permissions). When
| `permission` is an array the item shows if the user holds ANY of them.
| Groups with no visible children are dropped automatically.
*/

export const menuItems: MenuItemType[] = [
  {
    icon: 'dashboard',
    slug: 'main',
    label: 'Main',
    isTitle: true,
    children: [
      { url: '/admin/dashboard', slug: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
      { url: '/admin/tasks', slug: 'tasks', label: 'My Tasks', icon: 'checklist' },
    ],
  },
  {
    icon: 'briefcase',
    slug: 'operations',
    label: 'Operations',
    isTitle: true,
    children: [
      { url: '/admin/properties', slug: 'bible', label: 'Property Bible', icon: 'building', permission: 'bible.properties.view' },
      { url: '/admin/positions', slug: 'positions', label: 'Positions', icon: 'id-badge-2', permission: 'bible.positions.edit' },
      { url: '/admin/departments', slug: 'departments', label: 'Departments', icon: 'sitemap', permission: 'bible.departments.edit' },
      { url: '/admin/work-orders', slug: 'work-orders', label: 'Work Orders', icon: 'clipboard-list', permission: 'work_orders.view' },
      { url: '/admin/timesheets', slug: 'timesheets', label: 'Timesheets', icon: 'clock', permission: 'timesheets.view_history' },
      { url: '/admin/field-visits', slug: 'field-visits', label: 'Field Visits', icon: 'map-pin', permission: 'field_visits.view_own' },
      { url: '/admin/devices', slug: 'devices', label: 'Devices', icon: 'device-tablet', permission: 'devices.manage' },
    ],
  },
  {
    icon: 'users',
    slug: 'people',
    label: 'People & hiring',
    isTitle: true,
    children: [
      { url: '/admin/applicants', slug: 'applicants', label: 'Applicants', icon: 'user-plus', permission: 'people.applicants.view' },
      { url: '/admin/job-postings', slug: 'job-postings', label: 'Job Postings', icon: 'briefcase', permission: 'job_postings.manage' },
      { url: '/admin/people', slug: 'people', label: 'People', icon: 'users', permission: 'people.contractors.view' },
      { url: '/admin/pto', slug: 'pto', label: 'Time Off', icon: 'calendar', permission: 'pto.balances.view_own' },
    ],
  },
  {
    icon: 'file-invoice',
    slug: 'billing',
    label: 'Billing',
    isTitle: true,
    children: [
      { url: '/admin/imports', slug: 'imports', label: 'Hour Imports', icon: 'cloud-upload', permission: 'imports.upload' },
      { url: '/admin/invoices', slug: 'invoices', label: 'Invoices', icon: 'file-invoice', permission: 'invoices.view' },
    ],
  },
  {
    icon: 'packages',
    slug: 'inventory',
    label: 'Inventory',
    isTitle: true,
    children: [
      { url: '/admin/inventory', slug: 'inventory', label: 'Inventory', icon: 'packages', permission: 'inventory.items.view' },
      { url: '/admin/requests', slug: 'requests', label: 'Supply Requests', icon: 'package', permission: 'workflows.supply_request.initiate' },
    ],
  },
  {
    icon: 'checks',
    slug: 'approvals',
    label: 'Approvals',
    isTitle: true,
    children: [
      { url: '/admin/pay-increases', slug: 'pay-increases', label: 'Pay Increases', icon: 'trending-up', permission: 'workflows.pay_increase.initiate' },
      { url: '/admin/terminations', slug: 'terminations', label: 'Terminations', icon: 'user-x', permission: 'workflows.termination.initiate' },
      {
        url: '/admin/staffing-requests',
        slug: 'staffing-requests',
        label: 'Staffing Requests',
        icon: 'user-search',
        permission: ['workflows.more_staff.fulfill', 'workflows.more_staff.initiate'],
      },
      { url: '/admin/info-changes', slug: 'info-changes', label: 'Info Changes', icon: 'user-edit', permission: 'workflows.change_personal_info.verify' },
    ],
  },
  {
    icon: 'chart-bar',
    slug: 'reports',
    label: 'Reports',
    isTitle: true,
    children: [
      {
        url: '/admin/reports',
        slug: 'reports',
        label: 'Reports',
        icon: 'chart-bar',
        permission: ['reports.operational.view', 'reports.financial.view', 'reports.payroll.view'],
      },
    ],
  },
  {
    icon: 'book',
    slug: 'knowledge',
    label: 'Knowledge Base',
    isTitle: true,
    children: [
      { url: '/admin/kb', slug: 'kb-browse', label: 'Browse KB', icon: 'book', permission: 'kb.articles.view' },
      { url: '/admin/kb/articles', slug: 'kb-articles', label: 'Articles', icon: 'file-text', permission: 'kb.articles.edit' },
      { url: '/admin/kb/categories', slug: 'kb-categories', label: 'Categories', icon: 'category', permission: 'kb.categories.manage' },
      { url: '/admin/kb/tags', slug: 'kb-tags', label: 'Tags', icon: 'tag', permission: 'kb.categories.manage' },
      { url: '/admin/kb/feedback', slug: 'kb-feedback', label: 'Feedback', icon: 'message-circle', permission: 'kb.feedback.manage' },
    ],
  },
  {
    icon: 'shield-lock',
    slug: 'administration',
    label: 'Administration',
    isTitle: true,
    children: [
      { url: '/admin/audit', slug: 'audit', label: 'Audit Log', icon: 'shield-lock', permission: 'audit.activity_log.view' },
    ],
  },
]
