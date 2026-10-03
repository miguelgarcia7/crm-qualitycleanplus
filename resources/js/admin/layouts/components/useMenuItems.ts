import { menuItems, minuteMenuItems } from '@/layouts/components/data'
import type { MenuItemType } from '@/types'
import { usePage } from '@inertiajs/react'

// An item with a `permission` is hidden unless the user holds it (any of them,
// when an array).
export const hasPermission = (required: string | string[] | undefined, permissions: string[]): boolean => {
  if (!required) return true
  return Array.isArray(required) ? required.some((p) => permissions.includes(p)) : permissions.includes(required)
}

// Keep only items the user may see; a group is dropped once it has no children.
export const filterByPermission = (items: MenuItemType[], permissions: string[]): MenuItemType[] =>
  items
    .map((item): MenuItemType | null => {
      if (item.children) {
        const children = filterByPermission(item.children, permissions)
        return children.length ? { ...item, children } : null
      }
      return hasPermission(item.permission, permissions) ? item : null
    })
    .filter((item): item is MenuItemType => item !== null)

/**
 * The navigation for the surface this page is on, filtered to the user's
 * permissions: QC Minute's own menu on its domain, the back-office menu under
 * /admin, and nothing elsewhere on the main domain (marketing-side pages,
 * where /admin links don't belong).
 */
export const useMenuItems = (): MenuItemType[] => {
  const { url, props } = usePage()
  const { auth, surface } = props as { auth?: { permissions?: string[] }; surface?: string }
  const permissions = auth?.permissions ?? []

  if (surface === 'qcminute') return filterByPermission(minuteMenuItems, permissions)

  return url.startsWith('/admin') ? filterByPermission(menuItems, permissions) : []
}
