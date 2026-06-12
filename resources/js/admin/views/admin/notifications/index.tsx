import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { iconFor, NotificationsProp } from '@/layouts/components/TopBar/components/NotificationDropdown'

type HistoryRow = {
  id: string
  category: string | null
  message: string | null
  url: string | null
  read: boolean
  ago: string | null
  date: string | null
}

type Props = {
  history: HistoryRow[]
  notifications: NotificationsProp | null
}

/** Full notification history for the signed-in user (both surfaces). */
const Page = () => {
  const { history, notifications } = usePage().props as unknown as Props
  const base = notifications?.base ?? '/admin/notifications'
  const unread = history.filter((row) => !row.read).length

  const markAllRead = () => router.post(`${base}/read-all`, {}, { preserveScroll: true })

  return (
    <>
      <Head title="Notifications" />
      <PageBreadcrumb title="Notifications" subtitle="Account" />

      <div className="card">
        <div className="card-header flex items-center justify-between">
          <h4 className="card-title">
            All notifications
            {unread > 0 && <span className="badge badge-label bg-danger/15 text-danger ms-2">{unread} unread</span>}
          </h4>
          {unread > 0 && (
            <button type="button" onClick={markAllRead} className="btn btn-sm btn-light">
              Mark all read
            </button>
          )}
        </div>
        <div className="card-body p-0">
          {history.length === 0 ? (
            <div className="text-default-400 flex flex-col items-center gap-2 py-16 text-sm">
              <Icon icon="bell-off" className="size-8" />
              Nothing here yet — you&apos;ll see workflow, timesheet, and contract updates as they happen.
            </div>
          ) : (
            <ul className="divide-default-200 divide-y">
              {history.map((row) => {
                const marker = iconFor(row.category)
                return (
                  <li key={row.id}>
                    <Link
                      href={`${base}/${row.id}/open`}
                      className={cn('hover:bg-light/60 flex items-start gap-3 px-5 py-4', !row.read && 'bg-primary/3')}
                    >
                      <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-full', marker.className)}>
                        <Icon icon={marker.icon} className="size-4.5" />
                      </span>
                      <span className="min-w-0 grow">
                        <span className={cn('block text-sm', row.read ? 'text-default-500' : 'text-body-color font-medium')}>
                          {row.message}
                        </span>
                        <span className="text-default-400 text-xs" title={row.date ?? undefined}>
                          {row.ago}
                        </span>
                      </span>
                      {!row.read && <span className="bg-primary mt-2 size-2 shrink-0 rounded-full" aria-label="unread" />}
                    </Link>
                  </li>
                )
              })}
            </ul>
          )}
        </div>
      </div>
    </>
  )
}

export default Page
