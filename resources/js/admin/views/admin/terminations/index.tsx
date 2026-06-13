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

type Record = {
  id: number
  workflow_id: number
  person: string
  effective_date: string
  type: string
  reason: string
  status: string
  initiated_by: string | null
}

type Props = { records: Record[]; can: { initiate: boolean } }

const statusBadge = (status: string) =>
  status === 'Completed'
    ? 'bg-success/15 text-success'
    : status === 'Cancelled'
      ? 'bg-secondary/15 text-secondary'
      : status === 'Rejected'
        ? 'bg-danger/15 text-danger'
        : 'bg-warning/15 text-warning'

const columnHelper = createColumnHelper<Record>()

const Page = ({ records, can }: Props) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([{ id: 'effective_date', desc: true }])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const statuses = useMemo(() => [...new Set(records.map((r) => r.status))].sort(), [records])

  const columns = useMemo(
    () => [
      columnHelper.accessor('person', {
        header: 'Person',
        cell: ({ row }) => (
          <Link href={`/admin/terminations/${row.original.workflow_id}`} className="hover:text-primary font-semibold">
            {row.original.person}
          </Link>
        ),
      }),
      columnHelper.accessor('effective_date', {
        header: 'Effective',
      }),
      columnHelper.accessor('type', {
        header: 'Type',
      }),
      columnHelper.accessor('reason', {
        header: 'Reason',
      }),
      columnHelper.accessor('initiated_by', {
        header: 'Initiated by',
        cell: ({ row }) => row.original.initiated_by ?? '—',
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => <span className={cn('badge badge-label', statusBadge(row.original.status))}>{row.original.status}</span>,
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Record> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/terminations/${row.original.workflow_id}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="Open termination"
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
    data: records,
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
      <Head title="Terminations" />
      <PageBreadcrumb title="Terminations" subtitle="People" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search terminations..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>

            {can.initiate && (
              <Link href="/admin/terminations/create" className="btn bg-primary hover:bg-primary-hover text-white">
                <Icon icon="plus" />
                New Termination
              </Link>
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
              {statuses.map((status) => (
                <option key={status} value={status}>
                  {status}
                </option>
              ))}
            </select>
            <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No terminations yet." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="terminations"
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
