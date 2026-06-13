import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
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

type Req = {
  id: number; property: string; position: string; quantity_requested: number
  quantity_fulfilled: number; by_date: string; urgency: string; status: string
  reason: string; requested_by: string | null; is_overdue: boolean
}
type Props = { requests: Req[]; can: { decline: boolean; cancel: boolean } }

const urgencyBadge = (u: string) =>
  u === 'urgent'
    ? 'bg-danger/15 text-danger'
    : u === 'high'
      ? 'bg-warning/15 text-warning'
      : u === 'normal'
        ? 'bg-primary/15 text-primary'
        : 'bg-secondary/15 text-secondary'

const columnHelper = createColumnHelper<Req>()

const Page = ({ requests, can }: Props) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const urgencies = useMemo(() => [...new Set(requests.map((r) => r.urgency))], [requests])

  const decline = (id: number) => {
    const reason = window.prompt('Reason for declining?')
    if (reason) router.post(`/admin/staffing-requests/${id}/decline`, { reason }, { preserveScroll: true })
  }
  const cancel = (id: number) => {
    const reason = window.prompt('Reason for cancelling?')
    if (reason) router.post(`/admin/staffing-requests/${id}/cancel`, { reason }, { preserveScroll: true })
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('property', {
        header: 'Property',
        cell: ({ row }) => <span className="font-medium">{row.original.property}</span>,
      }),
      columnHelper.accessor('position', {
        header: 'Position',
        cell: ({ row }) => (
          <div>
            {row.original.position}
            <p className="text-default-400 text-xs">{row.original.reason}</p>
          </div>
        ),
      }),
      columnHelper.accessor('urgency', {
        header: 'Urgency',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => <span className={cn('badge badge-label capitalize', urgencyBadge(row.original.urgency))}>{row.original.urgency}</span>,
      }),
      columnHelper.accessor('quantity_fulfilled', {
        header: 'Progress',
        cell: ({ row }) => `${row.original.quantity_fulfilled} / ${row.original.quantity_requested}`,
      }),
      columnHelper.accessor('by_date', {
        header: 'By Date',
        cell: ({ row }) => (
          <>
            {row.original.by_date}
            {row.original.is_overdue && <span className="badge badge-label bg-danger/15 text-danger ms-2">Overdue</span>}
          </>
        ),
      }),
      columnHelper.accessor('requested_by', {
        header: 'Requested By',
        cell: ({ row }) => row.original.requested_by ?? '—',
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Req> }) => (
          <div className="flex justify-center gap-1.5">
            {can.decline && (
              <button
                className="btn btn-icon bg-danger hover:bg-danger-hover size-8 rounded-full text-white"
                onClick={() => decline(row.original.id)}
                title="Decline"
              >
                <Icon icon="x" className="text-base" />
              </button>
            )}
            {can.cancel && (
              <button
                className="btn btn-icon border-default-300 hover:border-default-400 border"
                onClick={() => cancel(row.original.id)}
                title="Cancel request"
              >
                <Icon icon="x" className="text-base" />
              </button>
            )}
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [can.decline, can.cancel],
  )

  const table = useReactTable({
    data: requests,
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
      <Head title="Staffing Requests" />
      <PageBreadcrumb title="Staffing Requests" subtitle="Recruiting" />

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

            <Link href="/admin/work-orders/create" className="btn bg-primary hover:bg-primary-hover text-white">
              <Icon icon="plus" />
              Place a contractor
            </Link>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="me-1 font-semibold text-nowrap">Filter By:</span>
            <select
              className="form-select w-auto"
              value={(table.getColumn('urgency')?.getFilterValue() as string) ?? 'All'}
              onChange={(e) => table.getColumn('urgency')?.setFilterValue(e.target.value === 'All' ? undefined : e.target.value)}
            >
              <option value="All">Urgency</option>
              {urgencies.map((urgency) => (
                <option key={urgency} value={urgency}>
                  {urgency}
                </option>
              ))}
            </select>
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No open staffing requests." />

        <div className="text-default-400 px-5 py-3 text-xs">
          Fulfill a request by creating a work order at the property and linking it to the request.
        </div>

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
    </>
  )
}

export default Page
