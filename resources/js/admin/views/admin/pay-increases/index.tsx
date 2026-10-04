import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { Head, router, useForm } from '@inertiajs/react'
import { cn } from '@/utils/helpers'
import {
  ColumnDef,
  createColumnHelper,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  Row as TableRow,
  SortingState,
  Table,
  useReactTable,
} from '@tanstack/react-table'
import { FormEvent, useEffect, useMemo, useState } from 'react'

type Rates = { pay_rate: number; bill_rate: number; ot_pay_rate: number; ot_bill_rate: number }
type PeriodOption = { id: number; label: string }
type Pending = {
  workflow_id: number
  contractor: string | null
  property: string | null
  position: string | null
  initiator: string | null
  reason: string | null
  pm_requested_increase: number
  current: Rates
  suggested: Rates
  periods: PeriodOption[]
  default_period_id: number | null
}
type WoOption = { id: number; contractor: string | null; property: string | null; position: string | null } & Rates & {
  periods: PeriodOption[]
  default_period_id: number | null
}
type Change = { pay_rate: number; bill_rate: number }
type Outcome = 'approved' | 'applied' | 'declined' | 'cancelled'
type Decided = {
  id: number
  contractor: string | null
  position: string | null
  property: string | null
  requested_by: string | null
  requested_at: string | null
  pm_requested_increase: number
  from: Change
  to: Change | null
  effective: string | null
  outcome: Outcome
  decided_by: string | null
  decided_at: string | null
  reason: string | null
  note: string | null
}
type Props = { pending: Pending[]; history: Decided[]; workOrders: WoOption[]; can: { approve: boolean; initiate: boolean } }

const money = (c: number) => `$${(c / 100).toFixed(2)}`

const OUTCOMES: Record<Outcome, { label: string; className: string }> = {
  approved: { label: 'Approved', className: 'bg-success/15 text-success' },
  applied: { label: 'Applied directly', className: 'bg-primary/15 text-primary' },
  declined: { label: 'Declined', className: 'bg-danger/15 text-danger' },
  cancelled: { label: 'Cancelled', className: 'bg-secondary/15 text-secondary' },
}

const TABS = [
  { key: 'waiting', label: 'Waiting for approval' },
  { key: 'history', label: 'History' },
] as const
type TabKey = (typeof TABS)[number]['key']

const pendingColumns = createColumnHelper<Pending>()
const historyColumns = createColumnHelper<Decided>()

/** Contractor name over "position · property" — shared by both tables. */
const Who = ({ name, position, property }: { name: string | null; position: string | null; property: string | null }) => (
  <div>
    <span className="font-medium">{name}</span>
    <p className="text-default-400 text-xs">
      {position}
      {property ? ` · ${property}` : ''}
    </p>
  </div>
)

/** A searchable, sortable, paginated table over in-memory rows. */
const usePagedTable = <T,>(data: T[], columns: ColumnDef<T, any>[], globalFilter: string, pageSize: number) => {
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize })

  return useReactTable({
    data,
    columns,
    state: { sorting, globalFilter, pagination: { ...pagination, pageSize } },
    onSortingChange: setSorting,
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    globalFilterFn: 'includesString',
  })
}

const Footer = <T,>({ table, itemsName }: { table: Table<T>; itemsName: string }) => {
  const { pageIndex, pageSize } = table.getState().pagination
  const totalItems = table.getFilteredRowModel().rows.length
  if (table.getRowModel().rows.length === 0) return null
  const start = pageIndex * pageSize + 1

  return (
    <div className="card-footer">
      <TablePagination
        totalItems={totalItems}
        start={start}
        end={Math.min(start + pageSize - 1, totalItems)}
        itemsName={itemsName}
        pageIndex={pageIndex}
        pageCount={table.getPageCount()}
        canPreviousPage={table.getCanPreviousPage()}
        canNextPage={table.getCanNextPage()}
        previousPage={table.previousPage}
        nextPage={table.nextPage}
        setPageIndex={table.setPageIndex}
        showInfo
      />
    </div>
  )
}

const Page = ({ pending, history, workOrders, can }: Props) => {
  // My Tasks' "Review" lands here with ?review=<id>: open that request's
  // approval form straight away, then tidy the URL.
  const [approving, setApproving] = useState<Pending | null>(() => {
    if (typeof window === 'undefined') return null
    const id = Number(new URLSearchParams(window.location.search).get('review'))
    return pending.find((p) => p.workflow_id === id) ?? null
  })
  useEffect(() => {
    if (typeof window !== 'undefined' && window.location.search.includes('review=')) {
      window.history.replaceState(null, '', window.location.pathname + window.location.hash)
    }
  }, [])
  const [creating, setCreating] = useState(false)
  const [search, setSearch] = useState('')
  const [outcome, setOutcome] = useState<Outcome | ''>('')
  const [pageSize, setPageSize] = useState(10)

  // The tab lives in the URL hash so History is linkable and survives refresh.
  const tabFromHash = (): TabKey => (typeof window !== 'undefined' && window.location.hash === '#history' ? 'history' : 'waiting')
  const [tab, setTab] = useState<TabKey>(tabFromHash)
  const selectTab = (key: TabKey) => {
    setTab(key)
    window.history.replaceState(null, '', `#${key}`)
  }

  const decline = (p: Pending) =>
    confirmAction({
      title: 'Decline pay increase',
      message: <>Decline the <strong>+{money(p.pm_requested_increase)}/hr</strong> request for <strong>{p.contractor}</strong>? {p.initiator ?? 'The property manager'} sees your reason.</>,
      input: { label: 'Reason', placeholder: 'Budget is set until January', required: true },
      confirmLabel: 'Decline',
      onConfirm: (reason) => router.post(`/admin/pay-increases/${p.workflow_id}/decline`, { reason }, { preserveScroll: true }),
    })

  const waitingCols = useMemo(
    () => [
      pendingColumns.accessor('contractor', {
        header: 'Contractor',
        cell: ({ row }) => <Who name={row.original.contractor} position={row.original.position} property={row.original.property} />,
      }),
      pendingColumns.accessor('initiator', { header: 'Requested By' }),
      pendingColumns.accessor('pm_requested_increase', {
        header: 'Asked For',
        cell: ({ row }) => (
          <div>
            <span className="font-medium">+{money(row.original.pm_requested_increase)}/hr</span>
            <p className="text-default-400 text-xs">
              bill {money(row.original.current.bill_rate)} → {money(row.original.current.bill_rate + row.original.pm_requested_increase)}
            </p>
          </div>
        ),
      }),
      pendingColumns.accessor('reason', {
        header: 'Reason',
        cell: ({ row }) => <span className="text-default-400">{row.original.reason}</span>,
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Pending> }) => (
          <div className="flex justify-center gap-1.5">
            {can.approve && (
              <>
                <button
                  className="btn btn-icon bg-success hover:bg-success-hover size-8 rounded-full text-white"
                  onClick={() => setApproving(row.original)}
                  title="Review & approve"
                >
                  <Icon icon="check" className="text-base" />
                </button>
                <button
                  className="btn btn-icon bg-danger hover:bg-danger-hover size-8 rounded-full text-white"
                  onClick={() => decline(row.original)}
                  title="Decline"
                >
                  <Icon icon="x" className="text-base" />
                </button>
              </>
            )}
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [can.approve],
  )

  const historyCols = useMemo(
    () => [
      historyColumns.accessor('contractor', {
        header: 'Contractor',
        cell: ({ row }) => <Who name={row.original.contractor} position={row.original.position} property={row.original.property} />,
      }),
      historyColumns.accessor((r) => `${r.requested_by ?? ''} ${r.reason ?? ''}`, {
        id: 'requested',
        header: 'Requested',
        cell: ({ row }) => {
          const r = row.original
          return (
            <div>
              <span>{r.outcome === 'applied' ? 'Direct raise' : r.requested_by}</span>
              <p className="text-default-400 text-xs">{r.requested_at}</p>
              {r.reason && <p className="text-default-400 text-xs italic">“{r.reason}”</p>}
            </div>
          )
        },
      }),
      historyColumns.display({
        id: 'change',
        header: 'Change',
        cell: ({ row }) => {
          const r = row.original
          if (r.to) {
            return (
              <div className="text-nowrap">
                <p>Pay {money(r.from.pay_rate)} → <span className="font-medium">{money(r.to.pay_rate)}</span></p>
                <p className="text-default-400 text-xs">Bill {money(r.from.bill_rate)} → {money(r.to.bill_rate)}</p>
              </div>
            )
          }
          return r.pm_requested_increase > 0 ? (
            <div className="text-nowrap">
              <p>+{money(r.pm_requested_increase)}/hr asked</p>
              <p className="text-default-400 text-xs">Bill {money(r.from.bill_rate)} → {money(r.from.bill_rate + r.pm_requested_increase)}</p>
            </div>
          ) : null
        },
      }),
      historyColumns.accessor((r) => `${OUTCOMES[r.outcome].label} ${r.note ?? ''}`, {
        id: 'outcome',
        header: 'Outcome',
        cell: ({ row }) => {
          const r = row.original
          return (
            <div className="max-w-64">
              <span className={`badge badge-label ${OUTCOMES[r.outcome].className}`}>{OUTCOMES[r.outcome].label}</span>
              {r.effective && <p className="text-default-400 mt-1 text-xs">From {r.effective}</p>}
              {r.note && <p className="text-default-500 mt-1 text-xs">{r.note}</p>}
            </div>
          )
        },
      }),
      historyColumns.accessor((r) => r.decided_by ?? '', {
        id: 'decided',
        header: 'Decided',
        cell: ({ row }) => (
          <div>
            <span>{row.original.decided_by ?? '—'}</span>
            <p className="text-default-400 text-xs">{row.original.decided_at}</p>
          </div>
        ),
      }),
    ],
    [],
  )

  const historyRows = useMemo(() => (outcome ? history.filter((h) => h.outcome === outcome) : history), [history, outcome])
  const waitingTable = usePagedTable(pending, waitingCols, search, pageSize)
  const historyTable = usePagedTable(historyRows, historyCols, search, pageSize)

  return (
    <>
      <Head title="Pay Increases" />
      <PageBreadcrumb title="Pay Increases" subtitle="Workflows" />

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
              {t.key === 'waiting' && pending.length > 0 && <span className="badge bg-warning/15 text-warning rounded-full px-2 text-xs">{pending.length}</span>}
            </button>
          ))}
        </nav>

        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder={tab === 'waiting' ? 'Search waiting requests...' : 'Search history...'}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>

            {tab === 'history' && (
              <select className="form-select w-48" value={outcome} onChange={(e) => setOutcome(e.target.value as Outcome | '')}>
                <option value="">All outcomes</option>
                {(Object.keys(OUTCOMES) as Outcome[]).map((o) => (
                  <option key={o} value={o}>{OUTCOMES[o].label}</option>
                ))}
              </select>
            )}

            {can.initiate && workOrders.length > 0 && (
              <button className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => setCreating(true)}>
                <Icon icon="plus" />
                New Pay Increase
              </button>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="text-default-400 text-sm text-nowrap">Rows per page</span>
            <select className="form-select w-20" value={pageSize} onChange={(e) => setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        {tab === 'waiting' ? (
          <>
            <DataTable table={waitingTable} emptyMessage="Nothing waiting — every request has been decided. See History." />
            <Footer table={waitingTable} itemsName="requests" />
          </>
        ) : (
          <>
            <DataTable table={historyTable} emptyMessage={outcome ? `No ${OUTCOMES[outcome].label.toLowerCase()} pay increases yet.` : 'No decided pay increases yet.'} />
            <Footer table={historyTable} itemsName="pay increases" />
          </>
        )}
      </div>

      {approving && <ApproveModal pending={approving} onClose={() => setApproving(null)} />}
      {creating && <CreateModal workOrders={workOrders} onClose={() => setCreating(false)} />}
    </>
  )
}

type RaiseData = {
  pay_rate: string
  bill_rate: string
  ot_pay_rate: string
  ot_bill_rate: string
  effective_period_id: number | string
  reason: string
}

const dollars = (cents: number) => (cents / 100).toFixed(2)
const toCents = (v: string) => Math.round((parseFloat(v) || 0) * 100)
const ot = (v: string) => dollars(Math.round(toCents(v) * 1.5))

const initialRaise = (rates: Rates, periodId: number | null): RaiseData => ({
  pay_rate: dollars(rates.pay_rate),
  bill_rate: dollars(rates.bill_rate),
  ot_pay_rate: dollars(rates.ot_pay_rate),
  ot_bill_rate: dollars(rates.ot_bill_rate),
  effective_period_id: periodId ?? '',
  reason: '',
})

/** "+$1.00 (6.3%)" against the current rate; red when it goes down. */
const Change = ({ from, to }: { from: number; to: number }) => {
  const diff = to - from
  if (diff === 0) return <span className="text-default-400 text-xs">No change</span>
  const pct = from > 0 ? ` (${((diff / from) * 100).toFixed(1)}%)` : ''
  return (
    <span className={`text-xs font-medium ${diff > 0 ? 'text-success' : 'text-danger'}`}>
      {diff > 0 ? '+' : '−'}
      {money(Math.abs(diff))}
      {pct}
    </span>
  )
}

/**
 * Current vs new pay and bill, overtime at 1.5× unless set by hand, the week it
 * starts, and a plain-language summary — shared by the recruiter's own raise
 * and approving a PM's request.
 */
const RaiseFields = ({
  current,
  contractor,
  property,
  periods,
  data,
  setData,
  errors,
  billFloor,
}: {
  current: Rates
  contractor: string | null
  property: string | null
  periods: PeriodOption[]
  data: RaiseData
  setData: (key: keyof RaiseData, value: string | number) => void
  errors: Partial<Record<keyof RaiseData, string>>
  billFloor?: number
}) => {
  const [manualOt, setManualOt] = useState(false)
  const pay = toCents(data.pay_rate)
  const bill = toCents(data.bill_rate)
  const week = periods.find((p) => String(p.id) === String(data.effective_period_id))

  const setRate = (key: 'pay_rate' | 'bill_rate', value: string) => {
    setData(key, value)
    if (!manualOt) setData(key === 'pay_rate' ? 'ot_pay_rate' : 'ot_bill_rate', ot(value))
  }

  const lowered = pay < current.pay_rate || bill < current.bill_rate
  const unchanged = pay === current.pay_rate && bill === current.bill_rate

  return (
    <>
      <div className="grid grid-cols-[5rem_minmax(0,1fr)_minmax(0,1fr)] items-center gap-x-3 gap-y-2">
        <span />
        <span className="font-semibold">Pay</span>
        <span className="font-semibold">Bill</span>

        <span className="text-default-400 text-sm">Current</span>
        <span className="text-default-500">{money(current.pay_rate)} / hr</span>
        <span className="text-default-500">{money(current.bill_rate)} / hr</span>

        <span className="text-sm font-semibold">New</span>
        <input type="number" step="0.01" min="0" className="form-input" value={data.pay_rate} onChange={(e) => setRate('pay_rate', e.target.value)} required />
        <input type="number" step="0.01" min="0" className="form-input" value={data.bill_rate} onChange={(e) => setRate('bill_rate', e.target.value)} required />

        <span />
        <Change from={current.pay_rate} to={pay} />
        <Change from={current.bill_rate} to={bill} />
      </div>
      {billFloor !== undefined && bill < billFloor && (
        <p className="text-danger text-sm">The bill rate must be at least {money(billFloor)} / hr to honor the property manager&apos;s request.</p>
      )}
      {errors.pay_rate && <p className="text-danger text-sm">{errors.pay_rate}</p>}
      {errors.bill_rate && <p className="text-danger text-sm">{errors.bill_rate}</p>}

      {manualOt ? (
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">Overtime pay ($/hr)</label>
            <input type="number" step="0.01" min="0" className="form-input" value={data.ot_pay_rate} onChange={(e) => setData('ot_pay_rate', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">Overtime bill ($/hr)</label>
            <input type="number" step="0.01" min="0" className="form-input" value={data.ot_bill_rate} onChange={(e) => setData('ot_bill_rate', e.target.value)} required />
          </div>
        </div>
      ) : (
        <div className="bg-light/60 text-default-500 flex flex-wrap items-center justify-between gap-2 rounded-md px-3 py-2 text-sm">
          <span>
            Overtime (1.5×): pay {money(toCents(data.ot_pay_rate))} · bill {money(toCents(data.ot_bill_rate))}
          </span>
          <button type="button" className="text-primary text-sm hover:underline" onClick={() => setManualOt(true)}>
            Set overtime by hand
          </button>
        </div>
      )}

      <div>
        <label className="form-label">Starts</label>
        <select className="form-select" value={data.effective_period_id} onChange={(e) => setData('effective_period_id', e.target.value)} required>
          {periods.map((p) => (
            <option key={p.id} value={p.id}>
              {p.label}
            </option>
          ))}
        </select>
        {week?.label.startsWith('This week') && (
          <p className="text-warning mt-1 text-xs">Hours already worked this week stay at the current rate; only new punches get the new one.</p>
        )}
        {errors.effective_period_id && <p className="text-danger mt-1 text-sm">{errors.effective_period_id}</p>}
      </div>

      {lowered ? (
        <p className="bg-danger/10 text-danger rounded-md px-3 py-2 text-sm">A pay increase can&apos;t lower the pay or bill rate.</p>
      ) : unchanged ? (
        <p className="bg-light/60 text-default-500 rounded-md px-3 py-2 text-sm">Enter the new pay or bill rate above.</p>
      ) : (
        <p className="bg-success/10 text-success rounded-md px-3 py-2 text-sm">
          {contractor} earns {money(pay)}/hr and {property} is billed {money(bill)}/hr
          {week ? ` from ${week.label.split(' — ')[1]?.split(' to ')[0]}` : ''}. Margin goes from{' '}
          {money(current.bill_rate - current.pay_rate)} to {money(bill - pay)}/hr.
        </p>
      )}
    </>
  )
}

const Modal = ({ title, subtitle, onClose, children }: { title: string; subtitle?: React.ReactNode; onClose: () => void; children: React.ReactNode }) => (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
    <div className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()}>
      <div className="card-header">
        <div>
          <h4 className="card-title">{title}</h4>
          {subtitle && <p className="text-default-400 text-sm">{subtitle}</p>}
        </div>
      </div>
      <div className="card-body max-h-[80vh] overflow-y-auto">{children}</div>
    </div>
  </div>
)

const ApproveModal = ({ pending, onClose }: { pending: Pending; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<RaiseData>(initialRaise(pending.suggested, pending.default_period_id))

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/pay-increases/${pending.workflow_id}/approve`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <Modal
      title={`Approve pay increase — ${pending.contractor}`}
      subtitle={`${pending.position} @ ${pending.property} · ${pending.initiator ?? 'The property manager'} asked for +${money(pending.pm_requested_increase)}/hr on the bill rate`}
      onClose={onClose}
    >
      <form onSubmit={submit} className="space-y-4">
        {pending.reason && <p className="text-default-500 border-default-200 border-s-2 ps-3 text-sm italic">{pending.reason}</p>}
        <RaiseFields
          current={pending.current}
          contractor={pending.contractor}
          property={pending.property}
          periods={pending.periods}
          data={data}
          setData={setData}
          errors={errors}
          billFloor={pending.current.bill_rate + pending.pm_requested_increase}
        />
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Approve raise</button>
        </div>
      </form>
    </Modal>
  )
}

const CreateModal = ({ workOrders, onClose }: { workOrders: WoOption[]; onClose: () => void }) => {
  const [woId, setWoId] = useState<number>(workOrders[0]?.id ?? 0)
  const wo = workOrders.find((w) => w.id === woId) ?? workOrders[0]
  const { data, setData, post, processing, errors, transform } = useForm<RaiseData>(initialRaise(wo, wo?.default_period_id ?? null))
  transform((d) => ({ ...d, work_order_id: woId }))

  const pickWo = (id: number) => {
    setWoId(id)
    const next = workOrders.find((w) => w.id === id)
    if (next) setData(initialRaise(next, next.default_period_id))
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/pay-increases', { preserveScroll: true, onSuccess: onClose })
  }

  // Grouped by property, so a long roster stays findable.
  const byProperty: Record<string, WoOption[]> = {}
  for (const w of workOrders) (byProperty[w.property ?? '—'] ??= []).push(w)

  return (
    <Modal title="New pay increase" subtitle="Applies right away — no approval needed." onClose={onClose}>
      <form onSubmit={submit} className="space-y-4">
        <div>
          <label className="form-label">Contractor</label>
          <select className="form-select" value={woId} onChange={(e) => pickWo(Number(e.target.value))}>
            {Object.entries(byProperty).map(([property, rows]) => (
              <optgroup key={property} label={property}>
                {rows.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.contractor} — {w.position}
                  </option>
                ))}
              </optgroup>
            ))}
          </select>
        </div>
        {wo && (
          <RaiseFields current={wo} contractor={wo.contractor} property={wo.property} periods={wo.periods} data={data} setData={setData} errors={errors} />
        )}
        <div>
          <label className="form-label">Reason</label>
          <input className="form-input" placeholder="Annual review" value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
        </div>
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Apply raise</button>
        </div>
      </form>
    </Modal>
  )
}

export default Page
