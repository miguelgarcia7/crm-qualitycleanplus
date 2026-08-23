import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, useForm } from '@inertiajs/react'
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
import { useMemo, useState } from 'react'

type Position = {
  id: number
  name: string
  notes: string | null
  is_active: boolean
  properties_count: number
  work_orders_count: number
}

type Props = {
  positions: Position[]
  can: { edit: boolean }
}

const emptyForm = { name: '', notes: '', is_active: true }

const columnHelper = createColumnHelper<Position>()

const Page = ({ positions, can }: Props) => {
  const [editing, setEditing] = useState<Position | null>(null)
  const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm(emptyForm)

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const startEdit = (p: Position) => {
    setEditing(p)
    clearErrors()
    setData({ name: p.name, notes: p.notes ?? '', is_active: p.is_active })
  }

  const cancelEdit = () => {
    setEditing(null)
    clearErrors()
    reset()
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      put(`/admin/positions/${editing.id}`, { preserveScroll: true, onSuccess: cancelEdit })
    } else {
      post('/admin/positions', { preserveScroll: true, onSuccess: () => reset() })
    }
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Position',
        cell: ({ row }) => (
          <div>
            <span className="font-semibold">{row.original.name}</span>
            {row.original.notes && <p className="text-default-400 max-w-60 truncate text-xs">{row.original.notes}</p>}
          </div>
        ),
      }),
      columnHelper.accessor('properties_count', {
        header: 'Properties with rates',
        cell: ({ row }) => (
          <span className={row.original.properties_count > 0 ? 'text-primary font-semibold' : ''}>{row.original.properties_count}</span>
        ),
      }),
      columnHelper.accessor('work_orders_count', {
        header: 'Work orders',
        cell: ({ row }) => (
          <span className={row.original.work_orders_count > 0 ? 'font-semibold' : ''}>{row.original.work_orders_count}</span>
        ),
      }),
      columnHelper.accessor('is_active', {
        header: 'Status',
        cell: ({ row }) => (
          <span className={cn('badge badge-label', row.original.is_active ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')}>
            {row.original.is_active ? 'Active' : 'Inactive'}
          </span>
        ),
      }),
      ...(can.edit
        ? [
            {
              header: 'Actions',
              cell: ({ row }: { row: TableRow<Position> }) => (
                <div className="flex justify-center gap-1.5">
                  <button
                    className="btn btn-icon border-default-300 hover:border-default-400 border"
                    onClick={() => startEdit(row.original)}
                    title="Edit position"
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
    [can.edit],
  )

  const table = useReactTable({
    data: positions,
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
      <Head title="Positions" />
      <PageBreadcrumb title="Positions" subtitle="Property Bible" />

      <div className={cn('gap-base grid', can.edit && 'lg:grid-cols-3')}>
        <div className={cn(can.edit && 'lg:col-span-2')}>
          <div className="card">
            <div className="card-header">
              <div className="flex flex-wrap gap-3">
                <div className="input-icon-group">
                  <Icon icon="search" className="input-icon" />
                  <input
                    className="form-input"
                    placeholder="Search positions..."
                    value={globalFilter}
                    onChange={(e) => setGlobalFilter(e.target.value)}
                  />
                </div>
              </div>
              <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
                <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
                  {[10, 25, 50].map((size) => (
                    <option key={size}>{size}</option>
                  ))}
                </select>
              </div>
            </div>

            <DataTable table={table} emptyMessage="No positions yet — create the first one to build the catalog." />

            {table.getRowModel().rows.length > 0 && (
              <div className="card-footer">
                <TablePagination
                  totalItems={totalItems}
                  start={start}
                  end={end}
                  itemsName="positions"
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
          <p className="text-default-400 mt-3 text-xs">
            One canonical name per position, shared by every property — renaming here updates it everywhere. Pay &amp; bill rates are set per
            property on each property&apos;s Rates tab. Positions can&apos;t be deleted once referenced; deactivate to retire one.
          </p>
        </div>

        {can.edit && (
          <div>
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">{editing ? `Edit "${editing.name}"` : 'New position'}</h4>
              </div>
              <div className="card-body">
                <form onSubmit={submit} className="space-y-3">
                  <div>
                    <label className="form-label">Name</label>
                    <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
                  </div>
                  <div>
                    <label className="form-label">Notes</label>
                    <textarea className="form-input w-full" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    {errors.notes && <p className="text-danger text-sm">{errors.notes}</p>}
                  </div>
                  <div className="flex items-center gap-2">
                    <input
                      id="pos-active"
                      type="checkbox"
                      className="form-checkbox"
                      checked={data.is_active}
                      onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    <label htmlFor="pos-active">Active (available for new rates and work orders)</label>
                  </div>
                  <div className="flex gap-2">
                    <button className="btn bg-primary hover:bg-primary-hover flex-1 py-2 font-semibold text-white" disabled={processing}>
                      {editing ? 'Save changes' : 'Create position'}
                    </button>
                    {editing && (
                      <button type="button" className="btn btn-light" onClick={cancelEdit}>
                        Cancel
                      </button>
                    )}
                  </div>
                </form>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

export default Page
