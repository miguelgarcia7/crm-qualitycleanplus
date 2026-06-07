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
    ],
  },
  {
    icon: 'table-column',
    slug: 'operations',
    label: 'Operations',
    isTitle: true,
    children: [
      { url: '#', slug: 'bible', label: 'Property Bible', icon: 'table-column', permission: 'bible.properties.view', isDisabled: true, badge: soon },
      { url: '#', slug: 'people', label: 'People', icon: 'user-circle', permission: 'people.contractors.view', isDisabled: true, badge: soon },
      { url: '#', slug: 'work-orders', label: 'Work Orders', icon: 'files', permission: 'work_orders.view', isDisabled: true, badge: soon },
      { url: '#', slug: 'timesheets', label: 'Timesheets', icon: 'layout', permission: 'timesheets.view_history', isDisabled: true, badge: soon },
      { url: '#', slug: 'invoices', label: 'Invoices', icon: 'files', permission: 'invoices.view', isDisabled: true, badge: soon },
      { url: '#', slug: 'workflows', label: 'Workflows', icon: 'sitemap', permission: 'workflows.pto.initiate', isDisabled: true, badge: soon },
      { url: '#', slug: 'inventory', label: 'Inventory', icon: 'components', permission: 'inventory.items.view', isDisabled: true, badge: soon },
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
