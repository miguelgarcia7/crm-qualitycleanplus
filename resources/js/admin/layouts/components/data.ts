import { type MenuItemType } from '@/types'

/*
| Back office sidebar menu. Items with a `permission` only render for users who
| have it (gated in Sidenav/AppMenu against the shared auth.permissions).
| Built sections have a real `url`; not-yet-built sections are `isDisabled`
| placeholders (url '#', "Soon" badge) and become real routes as phases land.
*/

const soon = { className: 'bg-primary', text: 'Soon' }

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
    icon: 'table-column',
    slug: 'operations',
    label: 'Operations',
    isTitle: true,
    children: [
      { url: '/admin/properties', slug: 'bible', label: 'Property Bible', icon: 'table-column', permission: 'bible.properties.view' },
      { url: '#', slug: 'people', label: 'People', icon: 'user-circle', permission: 'people.contractors.view', isDisabled: true, badge: soon },
      { url: '/admin/work-orders', slug: 'work-orders', label: 'Work Orders', icon: 'files', permission: 'work_orders.view' },
      { url: '#', slug: 'timesheets', label: 'Timesheets', icon: 'layout', permission: 'timesheets.view_history', isDisabled: true, badge: soon },
      { url: '/admin/invoices', slug: 'invoices', label: 'Invoices', icon: 'files', permission: 'invoices.view' },
      { url: '/admin/pay-increases', slug: 'pay-increases', label: 'Pay Increases', icon: 'trending-up', permission: 'workflows.pay_increase.initiate' },
      { url: '#', slug: 'workflows', label: 'Workflows', icon: 'sitemap', permission: 'workflows.pto.initiate', isDisabled: true, badge: soon },
      { url: '/admin/inventory', slug: 'inventory', label: 'Inventory', icon: 'components', permission: 'inventory.items.view' },
      { url: '/admin/requests', slug: 'requests', label: 'Requests', icon: 'package', permission: 'workflows.supply_request.initiate' },
      { url: '#', slug: 'reports', label: 'Reports', icon: 'table-column', permission: 'reports.operational.view', isDisabled: true, badge: soon },
    ],
  },
  {
    icon: 'password-user',
    slug: 'administration',
    label: 'Administration',
    isTitle: true,
    children: [
      { url: '#', slug: 'audit', label: 'Audit Log', icon: 'password-user', permission: 'audit.activity_log.view', isDisabled: true, badge: soon },
    ],
  },
  {
    icon: 'settings-2',
    slug: 'account',
    label: 'Account',
    isTitle: true,
    children: [
      { url: '/admin/settings/profile', slug: 'settings-profile', label: 'Profile', icon: 'user-circle' },
    ],
  },
]
