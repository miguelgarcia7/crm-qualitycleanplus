import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, router, useForm } from '@inertiajs/react'
import {
  ColumnFiltersState,
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

type Device = {
  id: number
  name: string
  property: string | null
  activation_code: string | null
  is_activated: boolean
  last_seen_at: string | null
  app_version: string | null
}

type Props = {
  devices: Device[]
  properties: { id: number; name: string }[]
}

const columnHelper = createColumnHelper<Device>()

const Page = ({ devices, properties }: Props) => {
  const { data, setData, post, processing, reset, errors } = useForm({ name: 'Front Desk Tablet', property_id: '' })

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const create = (e: React.FormEvent) => {
    e.preventDefault()
    post('/admin/devices', { preserveScroll: true, onSuccess: () => reset() })
  }

  const regenerate = (id: number) => router.post(`/admin/devices/${id}/regenerate`, {}, { preserveScroll: true })
  const revoke = (id: number) => {
    if (confirm('Revoke this device? Its token is invalidated and it must be re-paired.')) {
      router.delete(`/admin/devices/${id}`, { preserveScroll: true })
    }
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Device',
        cell: ({ row }) => <span className="font-semibold">{row.original.name}</span>,
      }),
      columnHelper.accessor('property', {
        header: 'Property',
        cell: ({ row }) => row.original.property ?? '—',
      }),
      columnHelper.accessor((d) => (d.is_activated ? 'Paired' : 'Pending'), {
        id: 'status',
        header: 'Status',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) =>
          row.original.is_activated ? (
            <span className="badge badge-label bg-success/15 text-success">Paired</span>
          ) : (
            <span className="text-warning font-mono text-base font-bold tracking-widest">{row.original.activation_code}</span>
          ),
      }),
      columnHelper.accessor('last_seen_at', {
        header: 'Last seen',
        enableSorting: false,
        cell: ({ row }) => row.original.last_seen_at ?? '—',
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Device> }) => (
          <div className="flex justify-center gap-1.5">
            <button
              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
              onClick={() => regenerate(row.original.id)}
              title="New activation code"
            >
              <Icon icon="refresh" className="text-base" />
            </button>
            <button
              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
              onClick={() => revoke(row.original.id)}
              title="Revoke device"
            >
              <Icon icon="trash" className="text-base" />
            </button>
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  )

  const table = useReactTable({
    data: devices,
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
      <Head title="Devices" />
      <PageBreadcrumb title="Devices" subtitle="Clock-In Tablets" />

      <div className="gap-base grid lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="card">
            <div className="card-header">
              <div className="flex flex-wrap gap-3">
                <div className="input-icon-group">
                  <Icon icon="search" className="input-icon" />
                  <input
                    className="form-input"
                    placeholder="Search devices..."
                    value={globalFilter}
                    onChange={(e) => setGlobalFilter(e.target.value)}
                  />
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
                <span className="me-1 font-semibold text-nowrap">Filter By:</span>
                <select
                  className="form-select w-auto"
                  value={(table.getColumn('status')?.getFilterValue() as string) ?? 'All'}
                  onChange={(e) => table.getColumn('status')?.setFilterValue(e.target.value === 'All' ? undefined : e.target.value)}
                >
                  <option value="All">Status</option>
                  <option value="Paired">Paired</option>
                  <option value="Pending">Pending</option>
                </select>
                <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
                  {[10, 25, 50].map((size) => (
                    <option key={size}>{size}</option>
                  ))}
                </select>
              </div>
            </div>

            <DataTable
              table={table}
              emptyMessage={
                <>
                  No devices yet. Create one, then pair the tablet at <code>/device</code>.
                </>
              }
            />

            {table.getRowModel().rows.length > 0 && (
              <div className="card-footer">
                <TablePagination
                  totalItems={totalItems}
                  start={start}
                  end={end}
                  itemsName="devices"
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
        </div>

        <div>
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Add a tablet</h4>
            </div>
            <div className="card-body">
              <form onSubmit={create} className="space-y-3">
                <div>
                  <label className="form-label">Name</label>
                  <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                  {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
                </div>
                <div>
                  <label className="form-label">Property</label>
                  <select className="form-select w-full" value={data.property_id} onChange={(e) => setData('property_id', e.target.value)} required>
                    <option value="">Select…</option>
                    {properties.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                  {errors.property_id && <p className="text-danger text-sm">{errors.property_id}</p>}
                </div>
                <button className="btn bg-primary hover:bg-primary-hover w-full py-2 font-semibold text-white" disabled={processing}>
                  Create device
                </button>
              </form>
              <p className="text-default-400 mt-4 text-xs">
                After creating, open{' '}
                <Link href="/device" className="text-primary">
                  the kiosk
                </Link>{' '}
                on the tablet and enter the code shown here.
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
