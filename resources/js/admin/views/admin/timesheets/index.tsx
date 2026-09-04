import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, Row as TableRow, SortingState, useReactTable } from '@tanstack/react-table'
import { useEffect, useMemo, useRef, useState } from 'react'

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

type Filters = {
  tab: string
  search: string
  property_id: number | null
  sort: string
  direction: string
  per_page: number
}

type Props = {
  timesheets: TimesheetRow[]
  pagination: PaginationMeta
  filters: Filters
  counts: Record<string, number>
  properties: { id: number; name: string }[]
  can_export: boolean
}

const TABS: { key: string; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'draft', label: 'Drafts' },
  { key: 'pending', label: 'Pending' },
  { key: 'billed', label: 'Billed' },
  { key: 'issues', label: 'Issues' },
]

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

/** Drop empties so the URL carries only what is actually filtering. */
const queryFrom = (filters: Filters, page: number): Record<string, string> => {
  const params: Record<string, string> = {}
  if (filters.tab !== 'all') params.tab = filters.tab
  if (filters.search !== '') params.search = filters.search
  if (filters.property_id) params.property_id = String(filters.property_id)
  if (filters.sort !== 'week_start') params.sort = filters.sort
  if (filters.direction !== 'desc') params.direction = filters.direction
  if (filters.per_page !== 25) params.per_page = String(filters.per_page)
  if (page > 1) params.page = String(page)
  return params
}

const columnHelper = createColumnHelper<TimesheetRow>()

const Page = ({ timesheets, pagination, filters, counts, properties, can_export }: Props) => {
  const [search, setSearch] = useState(filters.search)

  /**
   * Every control routes through here: the query string is the state, so a
   * filtered view is a link someone can send, and the back button steps
   * through filters the way people expect. Only the props that actually change
   * come back down the wire.
   */
  const visit = (changes: Partial<Filters>, page = 1, replace = false) => {
    router.get('/admin/timesheets', queryFrom({ ...filters, ...changes }, page), {
      preserveState: true,
      preserveScroll: true,
      // Tabs, filters and page moves push history, so Back steps through them.
      // Search replaces — nobody wants one history entry per keystroke.
      replace,
      only: ['timesheets', 'pagination', 'filters', 'counts'],
    })
  }

  // Debounced so typing does not fire a request per keystroke. The guard keeps
  // it from re-requesting what the server just sent back.
  const typed = useRef(false)
  useEffect(() => {
    if (!typed.current || search === filters.search) return
    const timer = setTimeout(() => visit({ search }, 1, true), 300)
    return () => clearTimeout(timer)
  }, [search]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => setSearch(filters.search), [filters.search])

  const sorting: SortingState = useMemo(
    () => [{ id: filters.sort, desc: filters.direction === 'desc' }],
    [filters.sort, filters.direction],
  )

  const columns = useMemo(
    () => [
      columnHelper.accessor('property', {
        header: 'Property',
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
        enableSorting: false,
        cell: ({ row }: { row: TableRow<TimesheetRow> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/properties/${row.original.property_id}/grid?week=${row.original.week_start}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="Open weekly grid">
              <Icon icon="layout" className="text-base" />
            </Link>
          </div>
        ),
      },
    ],
    [],
  )

  const table = useReactTable({
    data: timesheets,
    columns,
    state: { sorting },
    // The database orders and slices; the table only renders what it is given.
    manualSorting: true,
    manualPagination: true,
    pageCount: pagination.last_page,
    onSortingChange: (updater) => {
      const next = typeof updater === 'function' ? updater(sorting) : updater
      const [first] = next
      visit(first ? { sort: first.id, direction: first.desc ? 'desc' : 'asc' } : { sort: 'week_start', direction: 'desc' })
    },
    getCoreRowModel: getCoreRowModel(),
  })

  const exportQuery = new URLSearchParams(queryFrom(filters, 1)).toString()

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
              aria-selected={filters.tab === tab.key}
              onClick={() => visit({ tab: tab.key })}
              className={cn(
                'hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden',
                filters.tab === tab.key ? 'border-primary text-primary border-b' : '',
              )}>
              {tab.label}
              <span className="text-default-400 ms-1.5 text-xs">({counts[tab.key] ?? 0})</span>
            </button>
          ))}
        </nav>

        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search property or invoice #..."
                value={search}
                onChange={(e) => {
                  typed.current = true
                  setSearch(e.target.value)
                }}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            {properties.length > 1 && (
              <>
                <span className="me-1 font-semibold text-nowrap">Filter By:</span>
                <div className="input-icon-group">
                  <Icon icon="building" className="input-icon" />
                  <select
                    className="form-select !w-auto"
                    value={filters.property_id ?? ''}
                    onChange={(e) => visit({ property_id: e.target.value === '' ? null : Number(e.target.value) })}>
                    <option value="">All properties</option>
                    {properties.map((property) => (
                      <option key={property.id} value={property.id}>
                        {property.name}
                      </option>
                    ))}
                  </select>
                </div>
              </>
            )}
            <select className="form-select w-20" value={filters.per_page} onChange={(e) => visit({ per_page: Number(e.target.value) })}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
            {can_export && (
              <a
                href={`/admin/timesheets/export${exportQuery ? `?${exportQuery}` : ''}`}
                className="btn bg-primary hover:bg-primary-hover text-nowrap text-white">
                <Icon icon="download" className="me-1 size-4" /> Export Excel
              </a>
            )}
          </div>
        </div>

        <DataTable table={table} emptyMessage="No timesheets in this view yet." />

        {pagination.total > 0 && (
          <div className="card-footer">
            <ServerPagination meta={pagination} itemsName="timesheets" onPageChange={(page) => visit({}, page)} />
          </div>
        )}
      </div>
    </>
  )
}

export default Page
