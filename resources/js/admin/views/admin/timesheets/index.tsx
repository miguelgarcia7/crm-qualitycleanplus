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

type TimesheetRow = {
  id: number
  property: string
  property_id: number
  week_start: string
  week_end: string
  status: string
  status_label: string
  total_minutes: number
  total_bill: number
  submitted_at: string | null
  approved_at: string | null
  invoice_id: number | null
  invoice_number: string | null
}

type Props = {
  timesheets: TimesheetRow[]
  can_export: boolean
}

const TABS: { key: string; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'draft', label: 'Drafts' },
  { key: 'pending', label: 'Pending' },
  { key: 'billed', label: 'Billed' },
  { key: 'issues', label: 'Issues' },
]

const matchesTab = (status: string, tab: string) => {
  switch (tab) {
    case 'draft':
      return status === 'draft'
    case 'pending':
      return status === 'pending_approval'
    case 'billed':
      return ['approved', 'invoiced', 'invoice_sent'].includes(status)
    case 'issues':
      return ['declined', 'voided'].includes(status)
    default:
      return true
  }
}

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  pending_approval: 'bg-info/15 text-info',
  approved: 'bg-success/15 text-success',
  invoiced: 'bg-success/15 text-success',
  invoice_sent: 'bg-success/15 text-success',
  declined: 'bg-danger/15 text-danger',
  voided: 'bg-secondary/15 text-secondary',
}

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
const hours = (minutes: number) => (minutes / 60).toFixed(1)
const weekLabel = (start: string, end: string) => {
  const fmt = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  return `${fmt(start)} – ${fmt(end)}`
}

const columnHelper = createColumnHelper<TimesheetRow>()

const Page = ({ timesheets, can_export }: Props) => {
  const [activeTab, setActiveTab] = useState('all')
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const propertyOptions = useMemo(
    () => Array.from(new Set(timesheets.map((t) => t.property))).sort((a, b) => a.localeCompare(b)),
    [timesheets],
  )

  const counts = useMemo(() => {
    const result: Record<string, number> = {}
    for (const tab of TABS) {
      result[tab.key] = timesheets.filter((t) => matchesTab(t.status, tab.key)).length
    }
    return result
  }, [timesheets])

  const data = useMemo(() => timesheets.filter((t) => matchesTab(t.status, activeTab)), [timesheets, activeTab])

  const columns = useMemo(
    () => [
      columnHelper.accessor('property', {
        header: 'Property',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => <span className="font-semibold">{row.original.property}</span>,
      }),
      columnHelper.accessor('week_start', {
        header: 'Week',
        cell: ({ row }) => weekLabel(row.original.week_start, row.original.week_end),
      }),
      columnHelper.accessor('total_minutes', {
        header: 'Hours',
        cell: ({ row }) => hours(row.original.total_minutes),
      }),
      columnHelper.accessor('total_bill', {
        header: 'Billed',
        cell: ({ row }) => (row.original.total_bill > 0 ? money(row.original.total_bill) : '—'),
      }),
      columnHelper.accessor('submitted_at', {
        header: 'Submitted',
        cell: ({ row }) => row.original.submitted_at ?? '—',
      }),
      columnHelper.accessor('invoice_number', {
        header: 'Invoice',
        cell: ({ row }) =>
          row.original.invoice_id ? (
            <Link href={`/admin/invoices/${row.original.invoice_id}`} className="text-primary font-medium">
              {row.original.invoice_number}
            </Link>
          ) : (
            '—'
          ),
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ row }) => (
          <span className={cn('badge badge-label', statusBadge[row.original.status] ?? 'bg-light text-default-600')}>
            {row.original.status_label}
          </span>
        ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<TimesheetRow> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/properties/${row.original.property_id}/grid?week=${row.original.week_start}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="Open weekly grid"
            >
              <Icon icon="layout" className="text-base" />
            </Link>
          </div>
        ),
      },
    ],
    [],
  )

  const table = useReactTable({
    data,
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

  const selectTab = (key: string) => {
    setActiveTab(key)
    setPagination((p) => ({ ...p, pageIndex: 0 }))
  }

  return (
    <>
      <Head title="Timesheets" />
      <PageBreadcrumb title="Timesheets" subtitle="Operations" />

      <div className="card">
        <nav className="border-default-300 flex flex-wrap border-b px-4 pt-2" aria-label="Tabs" role="tablist">
          {TABS.map((tab) => (
            <button
              key={tab.key}
              type="button"
              role="tab"
              aria-selected={activeTab === tab.key}
              onClick={() => selectTab(tab.key)}
              className={cn(
                'hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden',
                activeTab === tab.key ? 'border-primary text-primary border-b' : '',
              )}
            >
              {tab.label}
              <span className="text-default-400 ms-1.5 text-xs">({counts[tab.key]})</span>
            </button>
          ))}
        </nav>

        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search timesheets..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            {propertyOptions.length > 1 && (
              <>
                <span className="me-1 font-semibold text-nowrap">Filter By:</span>
                <div className="input-icon-group">
                  <Icon icon="building" className="input-icon" />
                  <select
                    className="form-select !w-auto"
                    value={(table.getColumn('property')?.getFilterValue() as string) ?? 'All'}
                    onChange={(e) => {
                      table.getColumn('property')?.setFilterValue(e.target.value === 'All' ? undefined : e.target.value)
                      setPagination((p) => ({ ...p, pageIndex: 0 }))
                    }}
                  >
                    <option value="All">All properties</option>
                    {propertyOptions.map((name) => (
                      <option key={name} value={name}>
                        {name}
                      </option>
                    ))}
                  </select>
                </div>
              </>
            )}
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
            {can_export && (
              <a href="/admin/timesheets/export" className="btn bg-primary hover:bg-primary-hover text-nowrap text-white">
                <Icon icon="download" className="me-1 size-4" /> Export Excel
              </a>
            )}
          </div>
        </div>

        <DataTable table={table} emptyMessage="No timesheets in this view yet." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="timesheets"
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
