import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Entry = {
  id: number
  description: string
  event: string | null
  log_name: string | null
  subject_type: string | null
  subject_url: string | null
  causer: string | null
  causer_url: string | null
  created_at: string | null
}

type Props = {
  entries: Entry[]
  pagination: { page: number; last_page: number; total: number; from: number | null; to: number | null }
  filters: { q: string; log: string; event: string; subject: string }
  options: { logs: string[]; events: string[]; subjects: { value: string; label: string }[] }
}

const eventBadge: Record<string, string> = {
  created: 'bg-success/15 text-success',
  updated: 'bg-info/15 text-info',
  deleted: 'bg-danger/15 text-danger',
  login: 'bg-primary/15 text-primary',
}

const prettyLog = (log: string) => log.replace(/_/g, ' ')

const Page = ({ entries, pagination, filters, options }: Props) => {
  const [search, setSearch] = useState(filters.q)

  // Server-side filtering: every change reloads /admin/audit with the merged
  // query string (the log is too big to ship to the client).
  const apply = (overrides: Partial<Props['filters']> & { page?: number }) => {
    const params = { ...filters, q: search, page: 1, ...overrides }
    router.get(
      '/admin/audit',
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== 1)),
      { preserveState: true, preserveScroll: true },
    )
  }

  const submitSearch = (e: FormEvent) => {
    e.preventDefault()
    apply({})
  }

  return (
    <>
      <Head title="Audit Log" />
      <PageBreadcrumb title="Audit Log" subtitle="Administration" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <form onSubmit={submitSearch} className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search descriptions..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onBlur={() => search !== filters.q && apply({})}
              />
            </form>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <select className="form-select w-auto" value={filters.log} onChange={(e) => apply({ log: e.target.value })}>
              <option value="">All logs</option>
              {options.logs.map((log) => (
                <option key={log} value={log} className="capitalize">
                  {prettyLog(log)}
                </option>
              ))}
            </select>
            <select className="form-select w-auto" value={filters.event} onChange={(e) => apply({ event: e.target.value })}>
              <option value="">All events</option>
              {options.events.map((event) => (
                <option key={event} value={event}>
                  {event}
                </option>
              ))}
            </select>
            <select className="form-select w-auto" value={filters.subject} onChange={(e) => apply({ subject: e.target.value })}>
              <option value="">All subjects</option>
              {options.subjects.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-2xs uppercase">
                <th>When</th>
                <th>What</th>
                <th>Subject</th>
                <th>Event</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody>
              {entries.length ? (
                entries.map((entry) => (
                  <tr key={entry.id}>
                    <td className="text-nowrap">{entry.created_at}</td>
                    <td>
                      {entry.description}
                      {entry.log_name && (
                        <span className="text-default-400 ms-1.5 text-xs capitalize">({prettyLog(entry.log_name)})</span>
                      )}
                    </td>
                    <td>
                      {entry.subject_url ? (
                        <Link href={entry.subject_url} className="text-primary font-medium">
                          {entry.subject_type}
                        </Link>
                      ) : (
                        (entry.subject_type ?? '—')
                      )}
                    </td>
                    <td>
                      {entry.event ? (
                        <span className={cn('badge badge-label', eventBadge[entry.event] ?? 'bg-light text-default-600')}>
                          {entry.event}
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>
                      {entry.causer_url ? (
                        <Link href={entry.causer_url} className="hover:text-primary font-medium">
                          {entry.causer}
                        </Link>
                      ) : (
                        (entry.causer ?? 'System')
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} className="text-default-400 py-6 text-center">
                    No activity matches these filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {pagination.total > 0 && (
          <div className="card-footer flex items-center justify-between">
            <p className="text-default-400 text-sm">
              Showing {pagination.from}–{pagination.to} of {pagination.total.toLocaleString()} entries
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                className="btn btn-sm border-default-300 border"
                disabled={pagination.page <= 1}
                onClick={() => apply({ page: pagination.page - 1 })}
              >
                <Icon icon="chevron-left" className="size-4" /> Prev
              </button>
              <span className="text-default-400 self-center text-sm">
                Page {pagination.page} of {pagination.last_page}
              </span>
              <button
                type="button"
                className="btn btn-sm border-default-300 border"
                disabled={pagination.page >= pagination.last_page}
                onClick={() => apply({ page: pagination.page + 1 })}
              >
                Next <Icon icon="chevron-right" className="size-4" />
              </button>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

export default Page
