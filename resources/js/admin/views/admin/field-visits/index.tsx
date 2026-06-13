import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router } from '@inertiajs/react'
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

type Visit = {
  id: number
  recruiter: string | null
  property: string | null
  status: string
  check_in_at: string
  check_out_at: string | null
  duration: number | null
  inside_geofence: boolean
  late_close: boolean
}

type Props = {
  visits: Visit[]
  filter: string
  can: { viewAll: boolean }
}

const FILTERS = [
  { key: '', label: 'All' },
  { key: 'open', label: 'Still open' },
  { key: 'off_geofence', label: 'Off-geofence' },
  { key: 'late', label: 'Late close' },
]

const fmtDuration = (m: number | null) => (m === null ? '—' : m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`)

const columnHelper = createColumnHelper<Visit>()

const Page = ({ visits, filter, can }: Props) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const setFilter = (key: string) => router.get('/admin/field-visits', key ? { filter: key } : {}, { preserveState: true, preserveScroll: true })

  const columns = useMemo(
    () => [
      ...(can.viewAll
        ? [
            columnHelper.accessor('recruiter', {
              header: 'Recruiter',
              cell: ({ row }: { row: TableRow<Visit> }) => row.original.recruiter,
            }),
          ]
        : []),
      columnHelper.accessor('property', {
        header: 'Property',
        cell: ({ row }) => <span className="font-medium">{row.original.property}</span>,
      }),
      columnHelper.accessor('check_in_at', {
        header: 'Checked In',
        enableSorting: false,
      }),
      columnHelper.accessor('check_out_at', {
        header: 'Checked Out',
        enableSorting: false,
        cell: ({ row }) => row.original.check_out_at ?? <span className="badge badge-label bg-warning/15 text-warning">Open</span>,
      }),
      columnHelper.accessor((v) => v.duration ?? -1, {
        id: 'duration',
        header: 'Duration',
        cell: ({ row }: { row: TableRow<Visit> }) => fmtDuration(row.original.duration),
      }),
      {
        header: 'Flags',
        cell: ({ row }: { row: TableRow<Visit> }) => (
          <div className="space-x-1">
            {!row.original.inside_geofence && <span className="badge badge-label bg-danger/15 text-danger">Off-geofence</span>}
            {row.original.late_close && <span className="badge badge-label bg-warning/15 text-warning">Late close</span>}
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [can.viewAll],
  )

  const table = useReactTable({
    data: visits,
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
      <Head title="Field Visits" />
      <PageBreadcrumb title="Field Visits" subtitle="Recruiter Activity" />

      <div className="card">
        <nav className="border-default-300 flex flex-wrap border-b px-4 pt-2" aria-label="Tabs" role="tablist">
          {FILTERS.map((f) => (
            <button
              key={f.key}
              type="button"
              role="tab"
              aria-selected={filter === f.key}
              onClick={() => setFilter(f.key)}
              className={cn(
                'hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden',
                filter === f.key ? 'border-primary text-primary border-b' : '',
              )}
            >
              {f.label}
            </button>
          ))}
        </nav>

        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search visits..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>
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

        <DataTable table={table} emptyMessage="No visits found." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="visits"
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
