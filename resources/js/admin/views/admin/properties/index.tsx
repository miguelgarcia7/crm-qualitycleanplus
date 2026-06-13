import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'
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

type PropertyRow = {
  id: number
  name: string
  city: string | null
  state: string | null
  status: string
}

type Props = {
  properties: PropertyRow[]
  can: { create: boolean }
}

const statusBadge = (status: string) => (status === 'active' ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')

const columnHelper = createColumnHelper<PropertyRow>()

const Page = ({ properties, can }: Props) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Name',
        cell: ({ row }) => (
          <Link href={`/admin/properties/${row.original.id}`} className="hover:text-primary font-semibold">
            {row.original.name}
          </Link>
        ),
      }),
      columnHelper.accessor('city', {
        header: 'City',
        cell: ({ row }) => row.original.city ?? '—',
      }),
      columnHelper.accessor('state', {
        header: 'State',
        cell: ({ row }) => row.original.state ?? '—',
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => (
          <span className={cn('badge badge-label capitalize', statusBadge(row.original.status))}>{row.original.status}</span>
        ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<PropertyRow> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/properties/${row.original.id}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="View property"
            >
              <Icon icon="eye" className="text-base" />
            </Link>
            <Link
              href={`/admin/properties/${row.original.id}/grid`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="Weekly timesheet grid"
            >
              <Icon icon="calendar" className="text-base" />
            </Link>
          </div>
        ),
      },
    ],
    [],
  )

  const table = useReactTable({
    data: properties,
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
      <Head title="Property Bible" />
      <PageBreadcrumb title="Properties" subtitle="Property Bible" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search properties..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>

            {can.create && (
              <Link href="/admin/properties/create" className="btn bg-primary hover:bg-primary-hover text-white">
                <Icon icon="plus" />
                Add Property
              </Link>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="me-1 font-semibold text-nowrap">Filter By:</span>
            <select
              className="form-select w-auto min-w-36"
              value={(table.getColumn('status')?.getFilterValue() as string) ?? 'All'}
              onChange={(e) => table.getColumn('status')?.setFilterValue(e.target.value === 'All' ? undefined : e.target.value)}
            >
              <option value="All">Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No properties yet." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="properties"
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
    </>
  )
}

export default Page
