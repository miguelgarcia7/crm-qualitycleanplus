import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useRemember } from '@inertiajs/react'
import {
  createColumnHelper,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  PaginationState,
  Row as TableRow,
  SortingState,
  useReactTable,
} from '@tanstack/react-table'
import { useMemo } from 'react'

type PropertyRow = {
  id: number
  name: string
  city: string | null
  state: string | null
  pm_name: string | null
  status: string
}

type Props = {
  properties: PropertyRow[]
  can: { create: boolean }
}

type StatusFilter = 'active' | 'inactive' | 'all'

const STATUS_FILTERS: { key: StatusFilter; label: string }[] = [
  { key: 'active', label: 'Active' },
  { key: 'inactive', label: 'Inactive' },
  { key: 'all', label: 'All' },
]

const statusBadge = (status: string) => (status === 'active' ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')

const columnHelper = createColumnHelper<PropertyRow>()

const Page = ({ properties, can }: Props) => {
  // Remembered in the page's history entry (ADR-0029: bounded lists filter on
  // the client), so Back from a property lands on the same search and page.
  const [globalFilter, setGlobalFilter] = useRemember('', 'properties.search')
  const [status, setStatus] = useRemember<StatusFilter>('active', 'properties.status')
  const [sorting, setSorting] = useRemember<SortingState>([], 'properties.sorting')
  const [pagination, setPagination] = useRemember<PaginationState>({ pageIndex: 0, pageSize: 25 }, 'properties.pagination')

  const counts = {
    active: properties.filter((p) => p.status === 'active').length,
    inactive: properties.filter((p) => p.status !== 'active').length,
    all: properties.length,
  }
  const shown = useMemo(
    () => (status === 'all' ? properties : properties.filter((p) => (status === 'active' ? p.status === 'active' : p.status !== 'active'))),
    [properties, status],
  )

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Name',
        cell: ({ row }) => (
          <Link href={`/admin/properties/${row.original.id}`} className="hover:text-primary text-default-900 font-semibold">
            {row.original.name}
          </Link>
        ),
      }),
      // City, state and the PM in one searchable cell, so a search for
      // "Chicago" or the manager's name finds the property.
      columnHelper.accessor((p) => [p.city, p.state, p.pm_name].filter(Boolean).join(' '), {
        id: 'location',
        header: 'Location',
        sortingFn: (a, b) => `${a.original.state} ${a.original.city}`.localeCompare(`${b.original.state} ${b.original.city}`),
        cell: ({ row }) => (
          <div>
            <div>{[row.original.city, row.original.state].filter(Boolean).join(', ') || '—'}</div>
            {row.original.pm_name && <div className="text-default-400 text-sm">PM {row.original.pm_name}</div>}
          </div>
        ),
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ row }) => <span className={cn('badge badge-label capitalize', statusBadge(row.original.status))}>{row.original.status}</span>,
      }),
      {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: ({ row }: { row: TableRow<PropertyRow> }) => (
          <div className="flex items-center justify-center gap-1.5">
            <Link href={`/admin/properties/${row.original.id}/grid`} className="btn btn-sm border-default-300 hover:border-default-400 border whitespace-nowrap">
              <Icon icon="layout" className="size-4" /> Timesheet
            </Link>
            <Link
              href={`/admin/properties/${row.original.id}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="View property"
              aria-label={`View ${row.original.name}`}
            >
              <Icon icon="eye" className="text-base" />
            </Link>
          </div>
        ),
      },
    ],
    [],
  )

  const table = useReactTable({
    data: shown,
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
    // A new search or filter starts from the first page; Back keeps the page.
    autoResetPageIndex: false,
  })

  const pickStatus = (key: StatusFilter) => {
    setStatus(key)
    table.setPageIndex(0)
  }

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
                placeholder="Search name, city or PM…"
                aria-label="Search properties"
                value={globalFilter}
                onChange={(e) => {
                  setGlobalFilter(e.target.value)
                  table.setPageIndex(0)
                }}
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
            <div className="bg-light inline-flex rounded-lg p-0.5" role="group" aria-label="Show properties">
              {STATUS_FILTERS.map((f) => (
                <button
                  key={f.key}
                  type="button"
                  aria-pressed={status === f.key}
                  onClick={() => pickStatus(f.key)}
                  className={cn('rounded-md px-3 py-1 text-sm whitespace-nowrap', status === f.key ? 'bg-card text-default-900 font-semibold shadow-sm' : 'text-default-500')}
                >
                  {f.label} <span className="tabular-nums opacity-70">{counts[f.key]}</span>
                </button>
              ))}
            </div>
            <select className="form-select w-20" aria-label="Rows per page" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable
          table={table}
          emptyMessage={properties.length ? 'No properties match your search.' : 'No properties yet.'}
          onRowClick={(p) => router.visit(`/admin/properties/${p.id}`)}
        />

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
