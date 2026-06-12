import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link, router, usePage } from '@inertiajs/react'
import SimpleBar from 'simplebar-react'

export type NotificationItem = {
  id: string
  category: string | null
  message: string | null
  url: string | null
  read: boolean
  ago: string | null
}

export type NotificationsProp = {
  unread: number
  items: NotificationItem[]
  base: string
}

export const categoryIcon: Record<string, { icon: string; className: string }> = {
  workflows: { icon: 'arrows-exchange', className: 'bg-primary/15 text-primary' },
  timesheets: { icon: 'clock-check', className: 'bg-success/15 text-success' },
  contracts: { icon: 'file-alert', className: 'bg-warning/15 text-warning' },
}

export const iconFor = (category: string | null) =>
  categoryIcon[category ?? ''] ?? { icon: 'bell', className: 'bg-light text-default-500' }

/** The TopBar bell: unread badge + latest notifications (shared Inertia prop). */
const NotificationDropdown = () => {
  const { notifications } = usePage().props as unknown as { notifications: NotificationsProp | null }

  if (!notifications) return null

  const { unread, items, base } = notifications

  const markAllRead = () => router.post(`${base}/read-all`, {}, { preserveScroll: true })

  return (
    <div className="topbar-item hs-dropdown relative inline-flex [--auto-close:inside] [--placement:bottom-right]">
      <button className="topbar-link hs-dropdown-toggle relative flex items-center" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Notifications">
        <Icon icon="bell" className="topbar-link-icon" />
        {unread > 0 && (
          <span className="badge bg-danger absolute -end-px -top-4 size-4 rounded-full leading-0 text-white">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      <div className="hs-dropdown-menu min-w-80 space-y-0 p-0" role="menu" aria-orientation="vertical" aria-labelledby="dropdown-menu">
        <div className="border-default-300 border-b px-3 py-2">
          <div className="flex items-center justify-between">
            <h6 className="text-base font-semibold">Notifications</h6>
            {unread > 0 && (
              <button type="button" onClick={markAllRead} className="text-primary cursor-pointer text-xs font-medium">
                Mark all read
              </button>
            )}
          </div>
        </div>

        <SimpleBar style={{ maxHeight: '300px' }}>
          {items.length === 0 ? (
            <div className="text-default-400 flex flex-col items-center gap-2 px-4 py-8 text-sm">
              <Icon icon="bell-off" className="size-6" />
              You&apos;re all caught up.
            </div>
          ) : (
            items.map((item) => {
              const marker = iconFor(item.category)
              return (
                <Link key={item.id} href={`${base}/${item.id}/open`} className="dropdown-item gap-3 px-4.5 py-3 text-wrap">
                  <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-full', marker.className)}>
                    <Icon icon={marker.icon} className="size-4.5" />
                  </span>
                  <span className="grow">
                    <span className={cn('block text-sm', item.read ? 'text-default-400' : 'text-body-color font-medium')}>
                      {item.message}
                    </span>
                    <span className="text-default-400 text-xs">{item.ago}</span>
                  </span>
                  {!item.read && <span className="bg-primary mt-2 size-2 shrink-0 rounded-full" aria-label="unread" />}
                </Link>
              )
            })
          )}
        </SimpleBar>

        <div className="border-default-300 border-t p-2">
          <Link href={base} className="text-primary block w-full py-1 text-center text-sm font-medium">
            View all notifications
          </Link>
        </div>
      </div>
    </div>
  )
}

export default NotificationDropdown
