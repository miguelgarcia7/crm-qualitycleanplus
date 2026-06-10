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
}
type WoOption = { id: number; contractor: string | null; property: string | null; position: string | null } & Rates & { periods: PeriodOption[] }
type Props = { pending: Pending[]; workOrders: WoOption[]; can: { approve: boolean; initiate: boolean } }

const money = (c: number) => `$${(c / 100).toFixed(2)}`

const columnHelper = createColumnHelper<Pending>()

const Page = ({ pending, workOrders, can }: Props) => {
  const [approving, setApproving] = useState<Pending | null>(null)
  const [creating, setCreating] = useState(false)
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const decline = (workflowId: number) => {
    const reason = window.prompt('Reason for declining?')
    if (reason) router.post(`/admin/pay-increases/${workflowId}/decline`, { reason }, { preserveScroll: true })
  }

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
                  className="btn btn-icon btn-sm bg-success hover:bg-success-hover size-8 rounded-full text-white"
                  onClick={() => setApproving(row.original)}
                  title="Review & approve"
                >
                  <Icon icon="check" className="text-base" />
                </button>
                <button
                  className="btn btn-icon btn-sm bg-danger hover:bg-danger-hover size-8 rounded-full text-white"
                  onClick={() => decline(row.original.workflow_id)}
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
            <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
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

const RateInputs = ({ data, setData }: { data: any; setData: (k: any, v: any) => void }) => (
  <div className="grid grid-cols-2 gap-3">
    {(['pay_rate', 'bill_rate', 'ot_pay_rate', 'ot_bill_rate'] as const).map((k) => (
      <div key={k}>
        <label className="form-label capitalize">{k.replaceAll('_', ' ')} ($/hr)</label>
        <input type="number" step="0.01" min="0" className="form-input" value={data[k]} onChange={(e) => setData(k, e.target.value)} required />
      </div>
    ))}
  </div>
)

const ApproveModal = ({ pending, onClose }: { pending: Pending; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<{
    pay_rate: string; bill_rate: string; ot_pay_rate: string; ot_bill_rate: string; effective_period_id: number | string; reason: string
  }>({
    pay_rate: String(pending.suggested.pay_rate / 100),
    bill_rate: String(pending.suggested.bill_rate / 100),
    ot_pay_rate: String(pending.suggested.ot_pay_rate / 100),
    ot_bill_rate: String(pending.suggested.ot_bill_rate / 100),
    effective_period_id: pending.periods[0]?.id ?? ('' as number | string),
    reason: '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/pay-increases/${pending.workflow_id}/approve`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <div>
            <h4 className="card-title">Approve pay increase — {pending.contractor}</h4>
            <p className="text-default-400 text-sm">Current bill {money(pending.current.bill_rate)} · PM asked for +{money(pending.pm_requested_increase)}/hr</p>
          </div>
        </div>
        <div className="card-body max-h-[75vh] overflow-y-auto">
          <form onSubmit={submit} className="space-y-4">
            <RateInputs data={data} setData={setData} />
            {errors.bill_rate && <p className="text-danger text-sm">{errors.bill_rate}</p>}
            <div>
              <label className="form-label">Effective period</label>
              <select className="form-select" value={data.effective_period_id} onChange={(e) => setData('effective_period_id', e.target.value)} required>
                {pending.periods.map((p) => <option key={p.id} value={p.id}>{p.label}</option>)}
              </select>
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Approve &amp; create WO</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

const CreateModal = ({ workOrders, onClose }: { workOrders: WoOption[]; onClose: () => void }) => {
  const [woId, setWoId] = useState<number>(workOrders[0]?.id ?? 0)
  const wo = workOrders.find((w) => w.id === woId) ?? workOrders[0]
  const { data, setData, post, processing } = useForm<{
    work_order_id: number | string; pay_rate: string; bill_rate: string; ot_pay_rate: string; ot_bill_rate: string; effective_period_id: number | string; reason: string
  }>({
    work_order_id: wo?.id ?? ('' as number | string),
    pay_rate: wo ? String(wo.pay_rate / 100) : '',
    bill_rate: wo ? String(wo.bill_rate / 100) : '',
    ot_pay_rate: wo ? String(wo.ot_pay_rate / 100) : '',
    ot_bill_rate: wo ? String(wo.ot_bill_rate / 100) : '',
    effective_period_id: wo?.periods[0]?.id ?? ('' as number | string),
    reason: '',
  })

  const pickWo = (id: number) => {
    setWoId(id)
    const next = workOrders.find((w) => w.id === id)
    if (next) {
      setData((d) => ({ ...d, work_order_id: next.id, pay_rate: String(next.pay_rate / 100), bill_rate: String(next.bill_rate / 100), ot_pay_rate: String(next.ot_pay_rate / 100), ot_bill_rate: String(next.ot_bill_rate / 100), effective_period_id: next.periods[0]?.id ?? '' }))
    }
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/pay-increases', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">New pay increase</h4></div>
        <div className="card-body max-h-[75vh] overflow-y-auto">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Work order</label>
              <select className="form-select" value={woId} onChange={(e) => pickWo(Number(e.target.value))}>
                {workOrders.map((w) => <option key={w.id} value={w.id}>{w.contractor} — {w.position} @ {w.property}</option>)}
              </select>
            </div>
            <RateInputs data={data} setData={setData} />
            <div>
              <label className="form-label">Effective period</label>
              <select className="form-select" value={data.effective_period_id} onChange={(e) => setData('effective_period_id', e.target.value)} required>
                {(wo?.periods ?? []).map((p) => <option key={p.id} value={p.id}>{p.label}</option>)}
              </select>
            </div>
            <div>
              <label className="form-label">Reason</label>
              <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Apply</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
