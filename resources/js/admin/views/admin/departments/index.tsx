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

type Department = {
  id: number
  name: string
  is_active: boolean
  properties_count: number
}

type Props = {
  departments: Department[]
  can: { edit: boolean }
}

const emptyForm = { name: '', is_active: true }

const columnHelper = createColumnHelper<Department>()

const Page = ({ departments, can }: Props) => {
  const [editing, setEditing] = useState<Department | null>(null)
  const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm(emptyForm)

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const startEdit = (d: Department) => {
    setEditing(d)
    clearErrors()
    setData({ name: d.name, is_active: d.is_active })
  }

  const cancelEdit = () => {
    setEditing(null)
    clearErrors()
    reset()
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      put(`/admin/departments/${editing.id}`, { preserveScroll: true, onSuccess: cancelEdit })
    } else {
      post('/admin/departments', { preserveScroll: true, onSuccess: () => reset() })
    }
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Department',
        cell: ({ row }) => <span className="font-semibold">{row.original.name}</span>,
      }),
      columnHelper.accessor('properties_count', {
        header: 'Properties using it',
        cell: ({ row }) => (
          <span className={row.original.properties_count > 0 ? 'text-primary font-semibold' : ''}>{row.original.properties_count}</span>
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
              cell: ({ row }: { row: TableRow<Department> }) => (
                <div className="flex justify-center gap-1.5">
                  <button
                    className="btn btn-icon border-default-300 hover:border-default-400 border"
                    onClick={() => startEdit(row.original)}
                    title="Edit department"
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
    data: departments,
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
      <Head title="Departments" />
      <PageBreadcrumb title="Departments" subtitle="Property Bible" />

      <div className={cn('gap-base grid', can.edit && 'lg:grid-cols-3')}>
        <div className={cn(can.edit && 'lg:col-span-2')}>
          <div className="card">
            <div className="card-header">
              <div className="flex flex-wrap gap-3">
                <div className="input-icon-group">
                  <Icon icon="search" className="input-icon" />
                  <input
                    className="form-input"
                    placeholder="Search departments..."
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

            <DataTable table={table} emptyMessage="No departments yet — create the first one to build the catalog." />

            {table.getRowModel().rows.length > 0 && (
              <div className="card-footer">
                <TablePagination
                  totalItems={totalItems}
                  start={start}
                  end={end}
                  itemsName="departments"
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
            One canonical name per department, shared by every property — renaming here updates it everywhere. Manager name &amp; phone are
            set per property on each property&apos;s Departments tab. Departments can&apos;t be deleted once referenced; deactivate to retire
            one.
          </p>
        </div>

        {can.edit && (
          <div>
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">{editing ? `Edit "${editing.name}"` : 'New department'}</h4>
              </div>
              <div className="card-body">
                <form onSubmit={submit} className="space-y-3">
                  <div>
                    <label className="form-label">Name</label>
                    <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
                  </div>
                  <div className="flex items-center gap-2">
                    <input
                      id="dept-active"
                      type="checkbox"
                      className="form-checkbox"
                      checked={data.is_active}
                      onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    <label htmlFor="dept-active">Active (available on property Departments tabs)</label>
                  </div>
                  <div className="flex gap-2">
                    <button className="btn bg-primary hover:bg-primary-hover flex-1 py-2 font-semibold text-white" disabled={processing}>
                      {editing ? 'Save changes' : 'Create department'}
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
