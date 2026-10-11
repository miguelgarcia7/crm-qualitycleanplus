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
  time_source: string
  /** Contractors with an active work order here; null without work-order access. */
  contractors: number | null
  attention_count: number
  attention_top: string | null
}

type Props = {
  properties: PropertyRow[]
  can: { create: boolean }
}

type StatusFilter = 'active' | 'inactive' | 'all'
type QuickFilter = 'none' | 'attention'

const STATUS_FILTERS: { key: StatusFilter; label: string }[] = [
  { key: 'active', label: 'Active' },
  { key: 'inactive', label: 'Inactive' },
  { key: 'all', label: 'All' },
]

/** One-click filters. Weekly questions (approvals, hours) live on each property's Overview. */
const QUICK_FILTERS: { key: Exclude<QuickFilter, 'none'>; label: string; test: (p: PropertyRow) => boolean }[] = [
  { key: 'attention', label: 'Needs attention', test: (p) => p.attention_count > 0 },
]

const statusBadge = (status: string) => (status === 'active' ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')

const columnHelper = createColumnHelper<PropertyRow>()

const Page = ({ properties, can }: Props) => {
  // Remembered in the page's history entry (ADR-0029: bounded lists filter on
  // the client), so Back from a property lands on the same search and page.
  const [globalFilter, setGlobalFilter] = useRemember('', 'properties.search')
  const [status, setStatus] = useRemember<StatusFilter>('active', 'properties.status')
  const [quick, setQuick] = useRemember<QuickFilter>('none', 'properties.quick')
  // Busiest first: what needs someone, then by name.
  const [sorting, setSorting] = useRemember<SortingState>([{ id: 'attention', desc: true }], 'properties.sorting')
  const [pagination, setPagination] = useRemember<PaginationState>({ pageIndex: 0, pageSize: 25 }, 'properties.pagination')

  const showContractors = properties.some((p) => p.contractors !== null)

  const byStatus = useMemo(
    () => (status === 'all' ? properties : properties.filter((p) => (status === 'active' ? p.status === 'active' : p.status !== 'active'))),
    [properties, status],
  )
  const counts = {
    active: properties.filter((p) => p.status === 'active').length,
    inactive: properties.filter((p) => p.status !== 'active').length,
    all: properties.length,
  }
  const quickFilters = QUICK_FILTERS
  const shown = useMemo(() => {
    const filter = QUICK_FILTERS.find((f) => f.key === quick)
    return filter ? byStatus.filter(filter.test) : byStatus
  }, [byStatus, quick])

  const columns = useMemo(
    () => [
      // The name and PM together, so a search for the manager finds the property.
      columnHelper.accessor((p) => [p.name, p.pm_name].filter(Boolean).join(' '), {
        id: 'name',
        header: 'Property',
        sortingFn: (a, b) => a.original.name.localeCompare(b.original.name),
        cell: ({ row }) => (
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <Link href={`/admin/properties/${row.original.id}`} className="hover:text-primary text-default-900 font-semibold">
                {row.original.name}
              </Link>
              <span className={cn('badge badge-label text-xs', row.original.time_source === 'import' ? 'bg-info/15 text-info' : 'bg-primary/15 text-primary')}>
                {row.original.time_source === 'import' ? 'Hour import' : 'Clock-in'}
              </span>
            </div>
            {row.original.pm_name && <div className="text-default-400 text-sm whitespace-nowrap">PM {row.original.pm_name}</div>}
          </div>
        ),
      }),
      columnHelper.accessor((p) => [p.city, p.state].filter(Boolean).join(', '), {
        id: 'location',
        header: 'Location',
        sortingFn: (a, b) => `${a.original.state} ${a.original.city}`.localeCompare(`${b.original.state} ${b.original.city}`),
        cell: ({ getValue }) => <span className="whitespace-nowrap">{getValue() || '—'}</span>,
      }),
      ...(showContractors
        ? [
            columnHelper.accessor((p) => p.contractors ?? 0, {
              id: 'contractors',
              header: 'Contractors',
              sortDescFirst: true,
              cell: ({ getValue }) => <span className="tabular-nums">{getValue()}</span>,
            }),
          ]
        : []),
      columnHelper.accessor('attention_count', {
        id: 'attention',
        header: 'Needs attention',
        sortDescFirst: true,
        // Ties fall back to the name, so equal counts stay alphabetical.
        sortingFn: (a, b) => a.original.attention_count - b.original.attention_count || b.original.name.localeCompare(a.original.name),
        cell: ({ row }) =>
          row.original.attention_count > 0 ? (
            <div className="flex max-w-64 items-center gap-2">
              <span className="badge badge-label bg-warning/15 text-warning">{row.original.attention_count}</span>
              <span className="text-default-500 truncate text-sm">{row.original.attention_top}</span>
            </div>
          ) : (
            <span className="text-success inline-flex items-center gap-1 text-sm font-semibold">
              <Icon icon="check" className="size-4" /> All clear
            </span>
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
    [showContractors],
  )

  const table = useReactTable({
    data: shown,
    columns,
    // The Status column only earns its space when inactive properties are showing.
    state: { sorting, globalFilter, pagination, columnVisibility: { status: status !== 'active' } },
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
  const pickQuick = (key: Exclude<QuickFilter, 'none'>) => {
    setQuick(quick === key ? 'none' : key)
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
            {/* A toggle beside the status switch: click to narrow the list, click again for all. */}
            {quickFilters.map((f) => {
              const count = byStatus.filter(f.test).length
              const on = quick === f.key
              return (
                <button
                  key={f.key}
                  type="button"
                  aria-pressed={on}
                  onClick={() => pickQuick(f.key)}
                  className={cn(
                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1 text-sm whitespace-nowrap',
                    on ? 'border-primary bg-primary/10 text-default-900 font-semibold' : 'border-default-300 text-default-600 hover:border-default-400',
                  )}
                >
                  <Icon icon="alert-triangle" className={cn('size-4', count > 0 ? 'text-warning' : 'text-default-400')} />
                  {f.label}
                  <span className="font-semibold tabular-nums">{count}</span>
                </button>
              )
            })}
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
          emptyMessage={properties.length ? 'No properties match.' : 'No properties yet.'}
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
