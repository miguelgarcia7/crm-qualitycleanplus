import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { useMemo, useState } from 'react'

type Req = {
  id: number
  property: string | null
  position: string | null
  quantity_requested: number
  quantity_fulfilled: number
  by_date: string
  days_left: number
  urgency: string
  urgency_label: string
  status: string
  status_label: string
  reason: string | null
  notes: string | null
  requested_by: string | null
  requested_at: string | null
  is_overdue: boolean
  placed: string[]
}
type Decided = Req & { decided_by: string | null; decided_at: string | null; note: string | null }
type Props = { requests: Req[]; history: Decided[]; can: { place: boolean; decline: boolean; cancel: boolean } }

const URGENCY: Record<string, string> = {
  urgent: 'bg-danger/15 text-danger',
  high: 'bg-warning/15 text-warning',
  normal: 'bg-primary/15 text-primary',
  low: 'bg-secondary/15 text-secondary',
}
const OUTCOME: Record<string, string> = {
  fulfilled: 'bg-success/15 text-success',
  declined: 'bg-danger/15 text-danger',
  cancelled: 'bg-secondary/15 text-secondary',
}

const plural = (n: number, word: string | null) => `${n} ${word ?? ''}${n === 1 || !word ? '' : word.endsWith('s') ? '' : 's'}`

/** "in 10 days" / "today" / "3 days overdue" — the by-date in human terms. */
const due = (r: Req) => {
  if (r.days_left === 0) return 'today'
  if (r.days_left > 0) return `in ${r.days_left} day${r.days_left === 1 ? '' : 's'}`
  return `${-r.days_left} day${r.days_left === -1 ? '' : 's'} overdue`
}

const matches = (r: Req, q: string) =>
  !q || [r.property, r.position, r.requested_by, r.reason, r.notes, ...r.placed].some((v) => v?.toLowerCase().includes(q.toLowerCase()))

const TABS = [
  { key: 'open', label: 'Open' },
  { key: 'history', label: 'History' },
] as const
type TabKey = (typeof TABS)[number]['key']

const Page = ({ requests, history, can }: Props) => {
  const [search, setSearch] = useState('')
  const [urgency, setUrgency] = useState('')
  const [tab, setTab] = useState<TabKey>(() => (typeof window !== 'undefined' && window.location.hash === '#history' ? 'history' : 'open'))
  const selectTab = (key: TabKey) => {
    setTab(key)
    window.history.replaceState(null, '', `#${key}`)
  }

  const what = (r: Req) => (
    <>
      <strong>{plural(r.quantity_requested, r.position)}</strong> at <strong>{r.property}</strong>
    </>
  )
  const decline = (r: Req) =>
    confirmAction({
      title: 'Decline staffing request',
      message: <>Decline the request for {what(r)}? {r.requested_by ?? 'The property manager'} sees your reason.</>,
      input: { label: 'Reason', required: true },
      confirmLabel: 'Decline',
      onConfirm: (reason) => router.post(`/admin/staffing-requests/${r.id}/decline`, { reason }, { preserveScroll: true }),
    })
  const cancel = (r: Req) =>
    confirmAction({
      title: 'Cancel staffing request',
      message: <>Cancel the request for {what(r)}?</>,
      input: { label: 'Reason', required: true },
      confirmLabel: 'Cancel request',
      cancelLabel: 'Keep it',
      onConfirm: (reason) => router.post(`/admin/staffing-requests/${r.id}/cancel`, { reason }, { preserveScroll: true }),
    })

  const open = useMemo(() => requests.filter((r) => matches(r, search) && (!urgency || r.urgency === urgency)), [requests, search, urgency])
  const decided = useMemo(() => history.filter((r) => matches(r, search)), [history, search])

  return (
    <>
      <Head title="Staffing Requests" />
      <PageBreadcrumb title="Staffing Requests" subtitle="Recruiting" />

      <div className="card">
        <nav className="border-default-300 flex flex-wrap border-b px-4 pt-2" aria-label="Tabs" role="tablist">
          {TABS.map((t) => (
            <button
              key={t.key}
              type="button"
              role="tab"
              aria-selected={tab === t.key}
              onClick={() => selectTab(t.key)}
              className={cn(
                'hover:text-primary -mb-px inline-flex items-center gap-2 px-4 py-2 text-center font-medium focus:outline-hidden',
                tab === t.key ? 'border-primary text-primary border-b' : '',
              )}
            >
              {t.label}
              {t.key === 'open' && requests.length > 0 && <span className="badge bg-warning/15 text-warning rounded-full px-2 text-xs">{requests.length}</span>}
            </button>
          ))}
        </nav>

        <div className="card-header">
          <div className="input-icon-group">
            <Icon icon="search" className="input-icon" />
            <input className="form-input" placeholder="Search requests..." value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
          {tab === 'open' && (
            <select className="form-select w-auto min-w-40" value={urgency} onChange={(e) => setUrgency(e.target.value)}>
              <option value="">All urgencies</option>
              {Object.keys(URGENCY).map((u) => (
                <option key={u} value={u} className="capitalize">
                  {u[0].toUpperCase() + u.slice(1)}
                </option>
              ))}
            </select>
          )}
        </div>

        {tab === 'open' ? (
          <div className="card-body space-y-3">
            {open.length === 0 && (
              <p className="text-default-400 py-6 text-center">
                {requests.length === 0 ? 'No open staffing requests. Decided ones are under History.' : 'No requests match your search.'}
              </p>
            )}
            {open.map((r) => {
              const pct = Math.min(100, Math.round((r.quantity_fulfilled / Math.max(r.quantity_requested, 1)) * 100))
              return (
                <div key={r.id} className={cn('border-default-200 rounded-lg border p-4', r.is_overdue && 'border-danger/40')}>
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-base font-semibold">
                        {plural(r.quantity_requested, r.position)} <span className="text-default-500 font-normal">at {r.property}</span>
                      </p>
                      <p className="text-default-400 text-sm">
                        {r.requested_by ?? 'Property manager'} · requested {r.requested_at} · needed by {r.by_date}{' '}
                        <span className={cn(r.is_overdue && 'text-danger font-medium')}>({due(r)})</span>
                      </p>
                    </div>
                    <span className={cn('badge badge-label', URGENCY[r.urgency] ?? URGENCY.low)}>{r.urgency_label}</span>
                  </div>

                  {(r.reason || r.notes) && (
                    <div className="mt-3 space-y-1 text-sm">
                      {r.reason && (
                        <p>
                          <span className="text-default-400">Reason:</span> {r.reason}
                        </p>
                      )}
                      {r.notes && (
                        <p>
                          <span className="text-default-400">Notes from {r.requested_by?.split(' ')[0] ?? 'the PM'}:</span> {r.notes}
                        </p>
                      )}
                    </div>
                  )}

                  <div className="mt-3 flex items-center gap-3 text-sm">
                    <div className="bg-light h-1.5 flex-1 rounded-full">
                      <div className="bg-success h-1.5 rounded-full" style={{ width: `${pct}%` }} />
                    </div>
                    <span className="text-default-500 text-nowrap">
                      {r.quantity_fulfilled} of {r.quantity_requested} placed{r.placed.length > 0 && `: ${r.placed.join(', ')}`}
                    </span>
                  </div>

                  <div className="mt-3 flex flex-wrap justify-end gap-2">
                    {can.cancel && (
                      <button type="button" className="btn btn-light px-3 py-1.5 text-sm" onClick={() => cancel(r)}>
                        Cancel request
                      </button>
                    )}
                    {can.decline && (
                      <button type="button" className="btn bg-danger/10 text-danger hover:bg-danger px-3 py-1.5 text-sm hover:text-white" onClick={() => decline(r)}>
                        Decline
                      </button>
                    )}
                    {can.place && (
                      <Link href={`/admin/work-orders/create?staffing_request=${r.id}`} className="btn bg-primary hover:bg-primary-hover px-3 py-1.5 text-sm text-white">
                        <Icon icon="user-plus" className="size-4" /> Place contractor
                      </Link>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        ) : (
          <div className="table-wrapper">
            <table className="table table-hover text-sm">
              <thead className="thead-sm">
                <tr className="bg-light/25 text-xs uppercase">
                  <th>Request</th>
                  <th>Requested</th>
                  <th>Placed</th>
                  <th>Outcome</th>
                  <th>Decided</th>
                </tr>
              </thead>
              <tbody>
                {decided.length ? (
                  decided.map((r) => (
                    <tr key={r.id}>
                      <td>
                        <span className="font-medium">{plural(r.quantity_requested, r.position)}</span>
                        <p className="text-default-400 text-xs">{r.property}</p>
                      </td>
                      <td>
                        {r.requested_by ?? '—'}
                        <p className="text-default-400 text-xs">
                          {r.requested_at} · needed by {r.by_date}
                        </p>
                        {r.reason && <p className="text-default-400 text-xs italic">“{r.reason}”</p>}
                      </td>
                      <td>
                        {r.quantity_fulfilled} of {r.quantity_requested}
                        {r.placed.length > 0 && <p className="text-default-400 text-xs">{r.placed.join(', ')}</p>}
                      </td>
                      <td className="max-w-64">
                        <span className={cn('badge badge-label', OUTCOME[r.status] ?? OUTCOME.cancelled)}>{r.status_label}</span>
                        {r.note && <p className="text-default-500 mt-1 text-xs">{r.note}</p>}
                      </td>
                      <td>
                        {r.decided_by ?? '—'}
                        <p className="text-default-400 text-xs">{r.decided_at}</p>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={5} className="text-default-400 py-4 text-center">
                      {history.length === 0 ? 'No decided staffing requests yet.' : 'No requests match your search.'}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  )
}

export default Page
