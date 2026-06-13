import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
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

type Balance = { bucket: string; label: string; available: number; pending: number; allotted: number }
type Req = {
  id: number
  person?: string
  bucket: string
  start_date: string
  end_date: string
  hours: number
  status: string
  reason?: string
  cancellable?: boolean
}
type Other = { person_id: number; name: string; balances: Balance[] | null }

type Props = {
  myBalances: Balance[] | null
  noticePeriod: boolean
  myRequests: Req[]
  queue: Req[]
  others: Other[]
  can: { submit: boolean; approve: boolean; viewAll: boolean; adjust: boolean }
}

const statusBadge = (status: string) =>
  status === 'approved'
    ? 'bg-success/15 text-success'
    : status === 'pending'
      ? 'bg-warning/15 text-warning'
      : status === 'rejected'
        ? 'bg-danger/15 text-danger'
        : 'bg-secondary/15 text-secondary'

const bucketIcon: Record<string, string> = {
  vacation: 'calendar',
  scheduled: 'calendar-plus',
  unscheduled: 'clock',
}

/** Person cell with the leaves-table icon circle. */
const PersonCell = ({ name }: { name: string }) => (
  <div className="flex items-center gap-3">
    <div className="bg-light flex size-8 shrink-0 items-center justify-center rounded-full">
      <Icon icon="user" className="text-default-400" />
    </div>
    {name}
  </div>
)

const Page = ({ myBalances, noticePeriod, myRequests, queue, others, can }: Props) => {
  const form = useForm({ bucket: 'vacation', start_date: '', end_date: '', hours: 8, reason: '', notice_period_warning_acknowledged: false })
  const [adjustFor, setAdjustFor] = useState<Other | null>(null)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post('/admin/pto', { preserveScroll: true, onSuccess: () => form.reset('hours', 'reason', 'start_date', 'end_date') })
  }

  const approve = (id: number) => router.post(`/admin/pto/${id}/approve`, {}, { preserveScroll: true })
  const reject = (id: number) => {
    const reason = prompt('Reason for rejection?')
    if (reason) router.post(`/admin/pto/${id}/reject`, { reason }, { preserveScroll: true })
  }
  const cancel = (id: number) => {
    if (confirm('Cancel this PTO request? Hours return to your balance.')) {
      router.post(`/admin/pto/${id}/cancel`, {}, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head title="Time Off" />
      <PageBreadcrumb title="Time Off" subtitle="PTO" />

      {myBalances && (
        <div className="gap-base mb-6 grid grid-cols-1 sm:grid-cols-3">
          {myBalances.map((b) => (
            <div key={b.bucket} className="card">
              <div className="card-body">
                <div className="gap-base flex items-start">
                  <div className="bg-light flex size-11 shrink-0 items-center justify-center rounded-full">
                    <Icon icon={bucketIcon[b.bucket] ?? 'calendar'} className="text-default-800 text-xl" />
                  </div>
                  <div className="grow">
                    <h5 className="text-default-800 text-lg">
                      {b.available}
                      <small className="text-2xs">h available</small>
                    </h5>
                    <p className="text-default-400">{b.label}</p>
                    <p className="text-default-400 text-xs">
                      {b.pending}h pending · {b.allotted}h allotted
                    </p>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
        {can.submit && (
          <div className="card self-start">
            <div className="card-header">
              <h4 className="card-title">Request Time Off</h4>
            </div>
            <div className="card-body">
              {noticePeriod && (
                <div className="bg-warning/10 text-warning mb-3 rounded-lg p-3 text-sm">
                  A termination is in progress for you. Vacation during a notice period is generally not permitted.
                  <label className="mt-2 flex items-center gap-2">
                    <input
                      type="checkbox"
                      className="form-checkbox"
                      checked={form.data.notice_period_warning_acknowledged}
                      onChange={(e) => form.setData('notice_period_warning_acknowledged', e.target.checked)}
                    />
                    I acknowledge
                  </label>
                </div>
              )}
              <form onSubmit={submit} className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                  <label>
                    <span className="form-label">Bucket</span>
                    <select className="form-select w-full" value={form.data.bucket} onChange={(e) => form.setData('bucket', e.target.value)}>
                      <option value="vacation">Vacation</option>
                      <option value="scheduled">Scheduled</option>
                      <option value="unscheduled">Unscheduled</option>
                    </select>
                  </label>
                  <label>
                    <span className="form-label">Hours</span>
                    <input
                      type="number"
                      step="0.5"
                      min="0.5"
                      className="form-input w-full"
                      value={form.data.hours}
                      onChange={(e) => form.setData('hours', Number(e.target.value))}
                    />
                  </label>
                  <label>
                    <span className="form-label">Start</span>
                    <input
                      type="date"
                      className="form-input w-full"
                      value={form.data.start_date}
                      onChange={(e) => form.setData('start_date', e.target.value)}
                      required
                    />
                  </label>
                  <label>
                    <span className="form-label">End</span>
                    <input
                      type="date"
                      className="form-input w-full"
                      value={form.data.end_date}
                      onChange={(e) => form.setData('end_date', e.target.value)}
                      required
                    />
                  </label>
                </div>
                <label className="block">
                  <span className="form-label">Reason</span>
                  <input className="form-input w-full" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                </label>
                {form.errors.hours && <p className="text-danger text-sm">{form.errors.hours}</p>}
                <button className="btn bg-primary hover:bg-primary-hover w-full py-2 font-semibold text-white" disabled={form.processing}>
                  Submit request
                </button>
              </form>
            </div>
          </div>
        )}

        <div className={cn('card self-start', can.submit ? 'xl:col-span-2' : 'xl:col-span-3')}>
          <div className="card-header">
            <h4 className="card-title">My Requests</h4>
          </div>
          {myRequests.length === 0 ? (
            <div className="card-body">
              <p className="text-default-400 text-sm">No requests yet.</p>
            </div>
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead className="thead-sm">
                  <tr className="bg-light/25 text-xs uppercase">
                    <th>Bucket</th>
                    <th>Dates</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th className="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {myRequests.map((r) => (
                    <tr key={r.id}>
                      <td className="font-medium capitalize">{r.bucket}</td>
                      <td>
                        {r.start_date} → {r.end_date}
                      </td>
                      <td>{r.hours}h</td>
                      <td>
                        <span className={cn('badge badge-label capitalize', statusBadge(r.status))}>{r.status}</span>
                      </td>
                      <td>
                        <div className="flex justify-end">
                          {r.cancellable && (
                            <button
                              className="btn btn-icon border-default-300 hover:border-default-400 border"
                              onClick={() => cancel(r.id)}
                              title="Cancel request"
                            >
                              <Icon icon="x" className="text-base" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {can.approve && (
        <div className="card mt-6">
          <div className="card-header">
            <h4 className="card-title">Approval Queue</h4>
            {queue.length > 0 && <span className="badge badge-label bg-warning/15 text-warning">{queue.length} pending</span>}
          </div>
          {queue.length === 0 ? (
            <div className="card-body">
              <p className="text-default-400 text-sm">Nothing awaiting approval.</p>
            </div>
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead className="thead-sm">
                  <tr className="bg-light/25 text-xs uppercase">
                    <th>Staff</th>
                    <th>Bucket</th>
                    <th>Dates</th>
                    <th>Hours</th>
                    <th>Reason</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {queue.map((r) => (
                    <tr key={r.id}>
                      <td className="font-medium">
                        <PersonCell name={r.person ?? '—'} />
                      </td>
                      <td className="capitalize">{r.bucket}</td>
                      <td>
                        {r.start_date} → {r.end_date}
                      </td>
                      <td>{r.hours}h</td>
                      <td className="text-default-400">{r.reason ?? '—'}</td>
                      <td>
                        <div className="flex justify-center gap-1.5">
                          <button
                            className="btn btn-icon bg-success hover:bg-success-hover size-8 rounded-full text-white"
                            onClick={() => approve(r.id)}
                            title="Approve"
                          >
                            <Icon icon="check" className="text-base" />
                          </button>
                          <button
                            className="btn btn-icon bg-danger hover:bg-danger-hover size-8 rounded-full text-white"
                            onClick={() => reject(r.id)}
                            title="Reject"
                          >
                            <Icon icon="x" className="text-base" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {can.viewAll && <StaffBalances others={others} canAdjust={can.adjust} onAdjust={setAdjustFor} />}

      {adjustFor && <AdjustModal other={adjustFor} onClose={() => setAdjustFor(null)} />}
    </>
  )
}

const balanceColumnHelper = createColumnHelper<Other>()

const StaffBalances = ({ others, canAdjust, onAdjust }: { others: Other[]; canAdjust: boolean; onAdjust: (o: Other) => void }) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const availableFor = (other: Other, bucket: string) => other.balances?.find((b) => b.bucket === bucket)?.available ?? null

  const columns = useMemo(
    () => [
      balanceColumnHelper.accessor('name', {
        header: 'Staff',
        cell: ({ row }) => <PersonCell name={row.original.name} />,
      }),
      ...(['vacation', 'scheduled', 'unscheduled'] as const).map((bucket) =>
        balanceColumnHelper.accessor((other: Other) => availableFor(other, bucket) ?? -1, {
          id: bucket,
          header: bucket.charAt(0).toUpperCase() + bucket.slice(1),
          cell: ({ row }: { row: TableRow<Other> }) => {
            const available = availableFor(row.original, bucket)
            return available === null ? <span className="text-default-400">—</span> : `${available}h`
          },
        }),
      ),
      ...(canAdjust
        ? [
            {
              header: 'Actions',
              cell: ({ row }: { row: TableRow<Other> }) => (
                <div className="flex justify-center">
                  <button
                    className="btn btn-icon border-default-300 hover:border-default-400 border"
                    onClick={() => onAdjust(row.original)}
                    title="Adjust balance"
                  >
                    <Icon icon="edit" className="text-base" />
                  </button>
                </div>
              ),
            },
          ]
        : []),
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [canAdjust],
  )

  const table = useReactTable({
    data: others,
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
    <div className="card mt-6">
      <div className="card-header">
        <div className="flex flex-wrap gap-3">
          <div className="input-icon-group">
            <Icon icon="search" className="input-icon" />
            <input className="form-input" placeholder="Search staff..." value={globalFilter} onChange={(e) => setGlobalFilter(e.target.value)} />
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
          <h4 className="card-title me-auto md:hidden">Staff Balances</h4>
          <span className="text-default-400 text-sm text-nowrap">Rows per page</span>
          <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
            {[10, 25, 50].map((size) => (
              <option key={size}>{size}</option>
            ))}
          </select>
        </div>
      </div>

      <DataTable table={table} emptyMessage="No staff balances to show." />

      {table.getRowModel().rows.length > 0 && (
        <div className="card-footer">
          <TablePagination
            totalItems={totalItems}
            start={start}
            end={end}
            itemsName="staff"
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
  )
}

const AdjustModal = ({ other, onClose }: { other: Other; onClose: () => void }) => {
  const form = useForm({ person_id: other.person_id, vacation_hours: 0, scheduled_hours: 0, unscheduled_hours: 0, reason: '' })
  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post('/admin/pto/adjust', { preserveScroll: true, onSuccess: onClose })
  }
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <h4 className="card-title">Adjust Balance — {other.name}</h4>
        </div>
        <div className="card-body">
          <form onSubmit={submit} className="space-y-3">
            {(['vacation_hours', 'scheduled_hours', 'unscheduled_hours'] as const).map((k) => (
              <label key={k} className="block">
                <span className="form-label capitalize">{k.replace('_hours', '')} (± hours)</span>
                <input
                  type="number"
                  step="0.5"
                  className="form-input w-full"
                  value={form.data[k]}
                  onChange={(e) => form.setData(k, Number(e.target.value))}
                />
              </label>
            ))}
            <label className="block">
              <span className="form-label">Reason</span>
              <input className="form-input w-full" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} required />
            </label>
            {form.errors.reason && <p className="text-danger text-sm">{form.errors.reason}</p>}
            <div className="flex gap-2">
              <button className="btn bg-primary hover:bg-primary-hover px-5 py-2 font-semibold text-white" disabled={form.processing}>
                Apply
              </button>
              <button type="button" className="btn btn-light px-5 py-2" onClick={onClose}>
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
