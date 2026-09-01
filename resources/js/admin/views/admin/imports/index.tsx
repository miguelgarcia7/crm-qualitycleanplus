import { confirmAction } from '@/components/ConfirmHost'
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

type Batch = {
  id: number
  property: string | null
  period: string | null
  file_name: string
  status: string
  status_label: string
  uploaded_by: string | null
  created_at: string | null
  invoice: { id: number; number: string } | null
  stats: Record<string, number> | null
}

type Props = {
  batches: Batch[]
  can: { upload: boolean; rollback: boolean }
}

const statusBadge: Record<string, string> = {
  applied: 'bg-success/15 text-success',
  preview: 'bg-warning/15 text-warning',
  rolled_back: 'bg-secondary/15 text-secondary',
}

const columnHelper = createColumnHelper<Batch>()

const Page = ({ batches, can }: Props) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const rollback = (id: number, reimport: boolean) => {
    confirmAction({
      title: reimport ? 'Void and re-import' : 'Void and roll back',
      message: reimport ? (
        <>Void this import and start a corrected re-import for the same week? The current invoice is voided first.</>
      ) : (
        <>Void and roll back this import? The invoice is voided and every time entry it created is removed.</>
      ),
      confirmLabel: reimport ? 'Void and re-import' : 'Void and roll back',
      onConfirm: () => router.post(`/admin/imports/${id}/rollback`, { reimport }, { preserveScroll: true }),
    })
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('id', {
        header: '#',
        cell: ({ row }) => <span className="text-default-400">{row.original.id}</span>,
      }),
      columnHelper.accessor('property', {
        header: 'Property / Week',
        cell: ({ row }) => (
          <div>
            <div className="font-medium">{row.original.property ?? '—'}</div>
            <div className="text-default-400 text-xs">{row.original.period}</div>
          </div>
        ),
      }),
      columnHelper.accessor('file_name', {
        header: 'File',
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
      columnHelper.accessor('uploaded_by', {
        header: 'Uploaded',
        cell: ({ row }) => (
          <div>
            <div>{row.original.uploaded_by ?? '—'}</div>
            <div className="text-default-400 text-xs">{row.original.created_at}</div>
          </div>
        ),
      }),
      columnHelper.display({
        id: 'invoice',
        header: 'Invoice',
        cell: ({ row }) =>
          row.original.invoice ? (
            <Link href={`/admin/invoices/${row.original.invoice.id}`} className="text-primary font-semibold">
              {row.original.invoice.number}
            </Link>
          ) : (
            <span className="text-default-400">—</span>
          ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Batch> }) => {
          const b = row.original
          return (
            <div className="flex justify-center gap-1.5">
              <Link
                href={`/admin/imports/${b.id}`}
                className="btn bg-primary/15 text-primary hover:bg-primary hover:text-white"
              >
                {b.status === 'preview' ? 'Continue' : 'View'}
              </Link>
              {b.status === 'applied' && can.rollback && (
                <>
                  <button
                    className="btn bg-info/15 text-info hover:bg-info hover:text-white"
                    onClick={() => rollback(b.id, true)}
                  >
                    Re-import
                  </button>
                  <button
                    className="btn bg-danger/15 text-danger hover:bg-danger hover:text-white"
                    onClick={() => rollback(b.id, false)}
                  >
                    Void
                  </button>
                </>
              )}
            </div>
          )
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [can.rollback],
  )

  const table = useReactTable({
    data: batches,
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
      <Head title="Hour Imports" />
      <PageBreadcrumb title="Hour Imports" subtitle="Billing" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search imports..."
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
              <option value="preview">Preview</option>
              <option value="applied">Applied</option>
              <option value="rolled_back">Rolled back</option>
            </select>
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
            <Link href="/admin/imports/create" className="btn bg-primary text-nowrap text-white">
              New Import
            </Link>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No imports yet." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="imports"
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
