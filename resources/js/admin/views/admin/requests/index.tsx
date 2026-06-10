import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router, useForm } from '@inertiajs/react'
import {
  ColumnFiltersState,
  createColumnHelper,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  SortingState,
  useReactTable,
} from '@tanstack/react-table'
import { FormEvent, useMemo, useState } from 'react'

type Category = { id: number; name: string; slug: string; has_variants: boolean }
type VariantOption = { id: number; category_id: number; label: string }
type Contractor = { id: number; name: string }
type Req = {
  id: number; category: string; item: string; beneficiary: string; quantity: number
  charge_amount: number | null; status: string; status_label: string; is_new_item: boolean
  requested_by: string | null; step_key: string | null
}
type Props = {
  categories: Category[]; variants: VariantOption[]; contractors: Contractor[]
  mine: Req[]; queue: Req[]
  can: { initiate: boolean; fulfill: boolean; approve: boolean }
}

const money = (cents: number | null) => (cents == null ? '—' : `$${(cents / 100).toFixed(2)}`)

const statusBadge: Record<string, string> = {
  pending: 'bg-warning/15 text-warning',
  approved: 'bg-info/15 text-info',
  fulfilled: 'bg-success/15 text-success',
  denied: 'bg-danger/15 text-danger',
}

const columnHelper = createColumnHelper<Req>()

const Page = ({ categories, variants, contractors, mine, queue, can }: Props) => {
  const [create, setCreate] = useState(false)
  const [fulfilling, setFulfilling] = useState<Req | null>(null)

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const approve = (r: Req) => router.post(`/admin/requests/${r.id}/approve`, {}, { preserveScroll: true })
  const deny = (r: Req) => {
    const reason = window.prompt('Reason for denial?')
    if (reason) router.post(`/admin/requests/${r.id}/deny`, { reason }, { preserveScroll: true })
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('item', {
        header: 'Item',
        cell: ({ row }) => <span className="font-semibold">{row.original.item}</span>,
      }),
      columnHelper.accessor('beneficiary', {
        header: 'Beneficiary',
      }),
      columnHelper.accessor('quantity', {
        header: 'Qty',
      }),
      columnHelper.accessor('charge_amount', {
        header: 'Charge',
        cell: ({ row }) => money(row.original.charge_amount),
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => (
          <span className={cn('badge badge-label', statusBadge[row.original.status] ?? 'bg-secondary/15 text-secondary')}>
            {row.original.status_label}
          </span>
        ),
      }),
    ],
    [],
  )

  const table = useReactTable({
    data: mine,
    columns,
    state: { sorting, globalFilter, columnFilters, pagination },
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onColumnFiltersChange: setColumnFilters,
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    globalFilterFn: 'includesString',
    enableColumnFilters: true,
  })

  const pageIndex = table.getState().pagination.pageIndex
  const pageSize = table.getState().pagination.pageSize
  const totalItems = table.getFilteredRowModel().rows.length
  const start = totalItems === 0 ? 0 : pageIndex * pageSize + 1
  const end = Math.min(start + pageSize - 1, totalItems)

  return (
    <>
      <Head title="Requests" />
      <PageBreadcrumb title="Requests" subtitle="Supply &amp; Inventory" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search requests..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>

            {can.initiate && (
              <button className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => setCreate(true)}>
                <Icon icon="plus" />
                New Request
              </button>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="me-1 font-semibold text-nowrap">Filter By:</span>
            <select
              className="form-select w-auto"
              value={(table.getColumn('status')?.getFilterValue() as string) ?? 'All'}
              onChange={(e) => table.getColumn('status')?.setFilterValue(e.target.value === 'All' ? undefined : e.target.value)}
            >
              <option value="All">Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="fulfilled">Fulfilled</option>
              <option value="denied">Denied</option>
            </select>
            <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No requests yet." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="requests"
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

      {(can.fulfill || can.approve) && (
        <div className="card mt-6">
          <div className="card-header">
            <h4 className="card-title">Pending Queue</h4>
            {queue.length > 0 && <span className="badge badge-label bg-warning/15 text-warning">{queue.length} pending</span>}
          </div>
          {queue.length === 0 ? (
            <div className="card-body">
              <p className="text-default-400 text-sm">Nothing pending.</p>
            </div>
          ) : (
            <div className="table-wrapper">
              <table className="table table-hover">
                <thead className="thead-sm">
                  <tr className="bg-light/25 text-2xs uppercase">
                    <th>Item</th>
                    <th>Beneficiary</th>
                    <th>Requested by</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th className="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {queue.map((r) => (
                    <tr key={r.id}>
                      <td className="font-medium">
                        {r.item}
                        {r.is_new_item && <span className="badge badge-label bg-warning/15 text-warning ms-2">new item</span>}
                      </td>
                      <td>{r.beneficiary}</td>
                      <td>{r.requested_by}</td>
                      <td>{r.quantity}</td>
                      <td>
                        <span className={cn('badge badge-label', statusBadge[r.status] ?? 'bg-secondary/15 text-secondary')}>
                          {r.status_label}
                        </span>
                      </td>
                      <td>
                        <div className="flex justify-center gap-1.5">
                          {r.step_key === 'approve_new_item' && can.approve && (
                            <>
                              <button
                                className="btn btn-icon btn-sm bg-success hover:bg-success-hover size-8 rounded-full text-white"
                                onClick={() => approve(r)}
                                title="Approve"
                              >
                                <Icon icon="check" className="text-base" />
                              </button>
                              <button
                                className="btn btn-icon btn-sm bg-danger hover:bg-danger-hover size-8 rounded-full text-white"
                                onClick={() => deny(r)}
                                title="Deny"
                              >
                                <Icon icon="x" className="text-base" />
                              </button>
                            </>
                          )}
                          {r.step_key === 'fulfill' && can.fulfill && (
                            <button
                              className="btn btn-sm bg-success/15 text-success hover:bg-success hover:text-white"
                              onClick={() => setFulfilling(r)}
                            >
                              Fulfill
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
      )}

      {create && <CreateModal categories={categories} variants={variants} contractors={contractors} onClose={() => setCreate(false)} />}
      {fulfilling && <FulfillModal request={fulfilling} variants={variants} onClose={() => setFulfilling(null)} />}
    </>
  )
}

const CreateModal = ({ categories, variants, contractors, onClose }: { categories: Category[]; variants: VariantOption[]; contractors: Contractor[]; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<{
    category_id: number | string; mode: 'existing' | 'new'; item_variant_id: number | string
    proposed_item_name: string; proposed_description: string; estimated_cost: string
    beneficiary_type: string; beneficiary_person_id: number | string; quantity: number
    charge_amount: string; split_payments: string; needed_by: string; purpose: string; notes: string
  }>({
    category_id: categories[0]?.id ?? '', mode: 'existing', item_variant_id: '',
    proposed_item_name: '', proposed_description: '', estimated_cost: '',
    beneficiary_type: 'self', beneficiary_person_id: '', quantity: 1,
    charge_amount: '', split_payments: '', needed_by: '', purpose: '', notes: '',
  })

  const category = categories.find((c) => c.id === Number(data.category_id))
  const catVariants = useMemo(() => variants.filter((v) => v.category_id === Number(data.category_id)), [variants, data.category_id])
  const isUniformContractor = category?.slug === 'uniforms' && data.beneficiary_type === 'contractor'

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/requests', {
      preserveScroll: true,
      onSuccess: onClose,
    })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <h4 className="card-title">New Request</h4>
        </div>
        <div className="card-body max-h-[75vh] overflow-y-auto">
          <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="form-label">Category</label>
                <select className="form-select" value={data.category_id} onChange={(e) => setData('category_id', e.target.value)}>
                  {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
              <div>
                <label className="form-label">Quantity</label>
                <input type="number" min="1" className="form-input" value={data.quantity} onChange={(e) => setData('quantity', Number(e.target.value))} />
              </div>
            </div>

            <div className="flex gap-4 text-sm">
              <label className="flex items-center gap-2"><input type="radio" checked={data.mode === 'existing'} onChange={() => setData('mode', 'existing')} /> Existing item</label>
              <label className="flex items-center gap-2"><input type="radio" checked={data.mode === 'new'} onChange={() => setData('mode', 'new')} /> New item</label>
            </div>

            {data.mode === 'existing' ? (
              <div>
                <label className="form-label">Item</label>
                <select className="form-select" value={data.item_variant_id} onChange={(e) => setData('item_variant_id', e.target.value)} required>
                  <option value="">Select…</option>
                  {catVariants.map((v) => <option key={v.id} value={v.id}>{v.label}</option>)}
                </select>
                {errors.item_variant_id && <p className="text-danger mt-1 text-sm">{errors.item_variant_id}</p>}
              </div>
            ) : (
              <div className="space-y-3">
                <div>
                  <label className="form-label">Proposed item name</label>
                  <input className="form-input" value={data.proposed_item_name} onChange={(e) => setData('proposed_item_name', e.target.value)} required />
                  {errors.proposed_item_name && <p className="text-danger mt-1 text-sm">{errors.proposed_item_name}</p>}
                </div>
                <div>
                  <label className="form-label">Description</label>
                  <input className="form-input" value={data.proposed_description} onChange={(e) => setData('proposed_description', e.target.value)} />
                </div>
                <div>
                  <label className="form-label">Estimated cost ($)</label>
                  <input type="number" step="0.01" min="0" className="form-input" value={data.estimated_cost} onChange={(e) => setData('estimated_cost', e.target.value)} />
                </div>
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="form-label">For</label>
                <select className="form-select" value={data.beneficiary_type} onChange={(e) => setData('beneficiary_type', e.target.value)}>
                  <option value="self">Self</option>
                  <option value="contractor">Contractor</option>
                  <option value="general_office">General office</option>
                </select>
              </div>
              {data.beneficiary_type === 'contractor' && (
                <div>
                  <label className="form-label">Contractor</label>
                  <select className="form-select" value={data.beneficiary_person_id} onChange={(e) => setData('beneficiary_person_id', e.target.value)} required>
                    <option value="">Select…</option>
                    {contractors.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                  {errors.beneficiary_person_id && <p className="text-danger mt-1 text-sm">{errors.beneficiary_person_id}</p>}
                </div>
              )}
            </div>

            {isUniformContractor && (
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="form-label">Charge amount ($)</label>
                  <input type="number" step="0.01" min="0" className="form-input" value={data.charge_amount} onChange={(e) => setData('charge_amount', e.target.value)} />
                </div>
                <div>
                  <label className="form-label">Split (payments)</label>
                  <select className="form-select" value={data.split_payments} onChange={(e) => setData('split_payments', e.target.value)}>
                    <option value="">Auto</option>
                    {[1, 2, 3, 4].map((n) => <option key={n} value={n}>{n}</option>)}
                  </select>
                </div>
              </div>
            )}

            <div>
              <label className="form-label">Purpose / notes</label>
              <input className="form-input" value={data.purpose} onChange={(e) => setData('purpose', e.target.value)} />
            </div>

            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Submit</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

const FulfillModal = ({ request, variants, onClose }: { request: Req; variants: VariantOption[]; onClose: () => void }) => {
  const { data, setData, post, processing } = useForm<{ item_variant_id: number | string }>({ item_variant_id: '' })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/requests/${request.id}/fulfill`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <h4 className="card-title">Fulfill Request</h4>
        </div>
        <div className="card-body">
          <form onSubmit={submit} className="space-y-4">
            {request.is_new_item && (
              <div>
                <label className="form-label">Issue from item/variant</label>
                <select className="form-select" value={data.item_variant_id} onChange={(e) => setData('item_variant_id', e.target.value)} required>
                  <option value="">Select…</option>
                  {variants.map((v) => <option key={v.id} value={v.id}>{v.label}</option>)}
                </select>
                <p className="text-default-400 mt-1 text-xs">Create + receive the new item in Inventory first, then issue it here.</p>
              </div>
            )}
            {!request.is_new_item && <p className="text-default-500 text-sm">Issue {request.quantity} × {request.item} to {request.beneficiary}?</p>}
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Fulfill</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
