import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { Head, router, useForm } from '@inertiajs/react'
import {
  createColumnHelper,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  Row as TableRow,
  SortingState,
  useReactTable,
} from '@tanstack/react-table'
import { FormEvent, useMemo, useState } from 'react'

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
type Props = { pending: Pending[]; workOrders: WoOption[]; can: { approve: boolean; initiate: boolean } }

const money = (c: number) => `$${(c / 100).toFixed(2)}`

const columnHelper = createColumnHelper<Pending>()

const Page = ({ pending, workOrders, can }: Props) => {
  const [approving, setApproving] = useState<Pending | null>(null)
  const [creating, setCreating] = useState(false)
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const decline = (p: Pending) =>
    confirmAction({
      title: 'Decline pay increase',
      message: <>Decline the <strong>+{money(p.pm_requested_increase)}/hr</strong> request for <strong>{p.contractor}</strong>? {p.initiator ?? 'The property manager'} sees your reason.</>,
      input: { label: 'Reason', placeholder: 'Budget is set until January', required: true },
      confirmLabel: 'Decline',
      onConfirm: (reason) => router.post(`/admin/pay-increases/${p.workflow_id}/decline`, { reason }, { preserveScroll: true }),
    })

  const columns = useMemo(
    () => [
      columnHelper.accessor('contractor', {
        header: 'Contractor',
        cell: ({ row }) => (
          <div>
            <span className="font-medium">{row.original.contractor}</span>
            <p className="text-default-400 text-xs">{row.original.position}</p>
          </div>
        ),
      }),
      columnHelper.accessor('property', {
        header: 'Property',
      }),
      columnHelper.accessor('initiator', {
        header: 'Requested By',
      }),
      columnHelper.accessor('pm_requested_increase', {
        header: 'PM Increase',
        cell: ({ row }) => `${money(row.original.pm_requested_increase)}/hr`,
      }),
      columnHelper.accessor('reason', {
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

  const table = useReactTable({
    data: pending,
    columns,
    state: { sorting, globalFilter, pagination },
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    globalFilterFn: 'includesString',
  })

  const pageIndex = table.getState().pagination.pageIndex
  const pageSize = table.getState().pagination.pageSize
  const totalItems = table.getFilteredRowModel().rows.length
  const start = totalItems === 0 ? 0 : pageIndex * pageSize + 1
  const end = Math.min(start + pageSize - 1, totalItems)

  return (
    <>
      <Head title="Pay Increases" />
      <PageBreadcrumb title="Pay Increases" subtitle="Workflows" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search pending increases..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>

            {can.initiate && workOrders.length > 0 && (
              <button className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => setCreating(true)}>
                <Icon icon="plus" />
                New Pay Increase
              </button>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="text-default-400 text-sm text-nowrap">Rows per page</span>
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No pending pay increases." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="pay increases"
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
