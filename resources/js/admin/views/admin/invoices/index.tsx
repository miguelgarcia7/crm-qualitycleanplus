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

type Row = { id: number; invoice_number: string; property: string | null; issue_date: string; total: number; status: string; status_label: string }
type Props = { invoices: Row[] }

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  invoiced: 'bg-info/15 text-info',
  invoice_sent: 'bg-success/15 text-success',
  voided: 'bg-danger/15 text-danger',
}

const columnHelper = createColumnHelper<Row>()

const Page = ({ invoices }: Props) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const columns = useMemo(
    () => [
      columnHelper.accessor('invoice_number', {
        header: 'Number',
        cell: ({ row }) => (
          <Link href={`/admin/invoices/${row.original.id}`} className="hover:text-primary font-semibold">
            {row.original.invoice_number}
          </Link>
        ),
      }),
      columnHelper.accessor('property', {
        header: 'Property',
        cell: ({ row }) => row.original.property ?? '—',
      }),
      columnHelper.accessor('issue_date', {
        header: 'Issued',
      }),
      columnHelper.accessor('total', {
        header: 'Total',
        cell: ({ row }) => money(row.original.total),
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
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Row> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/invoices/${row.original.id}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="View invoice"
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
    data: invoices,
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
      <Head title="Invoices" />
      <PageBreadcrumb title="Invoices" subtitle="Billing" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search invoices..."
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
              <option value="draft">Draft</option>
              <option value="invoiced">Invoiced</option>
              <option value="invoice_sent">Invoice Sent</option>
              <option value="voided">Voided</option>
            </select>
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No invoices yet." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="invoices"
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
