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
  is_current: boolean
  status: string
  status_label: string
  total_minutes: number
  total_bill: number
  submitted_at: string | null
  approved_at: string | null
  waiting_days: number | null
  decline_reason: string | null
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
  oldest_pending_days: number | null
  properties: { id: number; name: string }[]
  can_export: boolean
}

const TABS: { key: string; label: string }[] = [
  { key: 'all', label: 'All timesheets' },
  { key: 'draft', label: 'Drafts' },
  { key: 'pending', label: 'Pending approval' },
  { key: 'declined', label: 'Declined' },
  { key: 'billed', label: 'Billed' },
  { key: 'voided', label: 'Voided' },
]

/** The line under each status card's count: what that pile means right now. */
const cardNote = (key: string, count: number, oldestPendingDays: number | null): { text: string; warn?: boolean } => {
  switch (key) {
    case 'all':
      return { text: 'Every past week' }
    case 'draft':
      return { text: count ? 'Not sent for approval' : 'None' }
    case 'pending':
      return oldestPendingDays === null
        ? { text: 'Nothing waiting' }
        : { text: oldestPendingDays === 0 ? 'Oldest sent today' : `Oldest waiting ${oldestPendingDays} ${oldestPendingDays === 1 ? 'day' : 'days'}`, warn: oldestPendingDays >= 3 }
    case 'declined':
      return count ? { text: 'Back with the recruiter', warn: true } : { text: 'Nothing to fix' }
    case 'billed':
      return { text: 'Approved or invoiced' }
    default:
      return { text: count ? 'Not billed' : 'None' }
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
const hours = (minutes: number) => (minutes / 60).toFixed(2)
/** "Oct 5 – 11", or "Sep 28 – Oct 4" across a month; the year only when it isn't this one. */
const weekLabel = (start: string, end: string) => {
  const s = new Date(`${start}T12:00:00`)
  const e = new Date(`${end}T12:00:00`)
  const month = (d: Date) => d.toLocaleDateString('en-US', { month: 'short' })
  const range = s.getMonth() === e.getMonth() ? `${month(s)} ${s.getDate()} – ${e.getDate()}` : `${month(s)} ${s.getDate()} – ${month(e)} ${e.getDate()}`
  return e.getFullYear() === new Date().getFullYear() ? range : `${range}, ${e.getFullYear()}`
}
const waiting = (days: number) => (days === 0 ? 'Waiting since today' : `Waiting ${days} ${days === 1 ? 'day' : 'days'}`)

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

const Page = ({ timesheets, pagination, filters, counts, oldest_pending_days, properties, can_export }: Props) => {
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
      only: ['timesheets', 'pagination', 'filters', 'counts', 'oldest_pending_days'],
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
        cell: ({ row }) => (
          <Link href={`/admin/properties/${row.original.property_id}`} className="hover:text-primary text-default-900 font-semibold">
            {row.original.property}
          </Link>
        ),
      }),
      columnHelper.accessor('week_start', {
        header: 'Week',
        cell: ({ row }) => (
          <span className="whitespace-nowrap">
            {weekLabel(row.original.week_start, row.original.week_end)}
            {row.original.is_current && <span className="badge badge-label bg-light text-default-600 ms-2">This week</span>}
          </span>
        ),
      }),
      columnHelper.accessor('total_minutes', {
        header: 'Hours',
        meta: { align: 'end' },
        cell: ({ row }) => <span className="tabular-nums">{hours(row.original.total_minutes)}</span>,
      }),
      columnHelper.accessor('total_bill', {
        header: 'Billed',
        meta: { align: 'end' },
        cell: ({ row }) => <span className="tabular-nums">{row.original.total_bill > 0 ? money(row.original.total_bill) : '—'}</span>,
      }),
      columnHelper.accessor('submitted_at', {
        header: 'Submitted',
        cell: ({ row }) => <span className="whitespace-nowrap">{row.original.submitted_at ?? '—'}</span>,
      }),
      columnHelper.accessor('approved_at', {
        header: 'Approved',
        cell: ({ row }) => <span className="whitespace-nowrap">{row.original.approved_at ?? '—'}</span>,
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
        cell: ({ row }) => {
          const t = row.original
          return (
            <div className="max-w-56">
              <span className={cn('badge badge-label whitespace-nowrap', statusBadge[t.status] ?? 'bg-light text-default-600')}>{t.status_label}</span>
              {t.waiting_days !== null && (
                <span className={cn('mt-1 block text-xs whitespace-nowrap', t.waiting_days >= 3 ? 'text-warning' : 'text-default-500')}>{waiting(t.waiting_days)}</span>
              )}
              {t.decline_reason && (
                <span className="text-default-500 mt-1 block truncate text-xs" title={t.decline_reason}>
                  {t.decline_reason}
                </span>
              )}
            </div>
          )
        },
      }),
      {
        header: 'Actions',
        enableSorting: false,
        cell: ({ row }: { row: TableRow<TimesheetRow> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/properties/${row.original.property_id}/grid?week=${row.original.week_start}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="Open weekly grid"
              aria-label={`Open the ${row.original.property} grid for ${weekLabel(row.original.week_start, row.original.week_end)}`}>
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

      <div className="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" role="group" aria-label="Show timesheets">
        {TABS.map((tab) => {
          const on = filters.tab === tab.key
          const count = counts[tab.key] ?? 0
          const note = cardNote(tab.key, count, oldest_pending_days)
          return (
            <button
              key={tab.key}
              type="button"
              aria-pressed={on}
              // Clicking the selected card again goes back to everything.
              onClick={() => visit({ tab: on && tab.key !== 'all' ? 'all' : tab.key })}
              className={cn(
                'bg-card rounded-xl border-2 px-4 py-3 text-start shadow-sm transition-colors',
                on ? 'border-primary' : 'hover:border-default-300 border-transparent',
              )}>
              <span className="text-default-500 block text-sm font-medium">{tab.label}</span>
              <span className="text-default-900 block text-2xl font-semibold tabular-nums">{count}</span>
              <span className={cn('block text-xs', note.warn ? 'text-warning' : 'text-default-500')}>{note.text}</span>
            </button>
          )
        })}
      </div>

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search property or invoice #…"
                aria-label="Search timesheets"
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
                    aria-label="Property"
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
            <select className="form-select w-20" aria-label="Rows per page" value={filters.per_page} onChange={(e) => visit({ per_page: Number(e.target.value) })}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
            {can_export && (
              <a
                href={`/admin/timesheets/export${exportQuery ? `?${exportQuery}` : ''}`}
                className="btn border-default-300 hover:border-default-400 border text-nowrap">
                <Icon icon="download" className="size-4" /> Export Excel
              </a>
            )}
          </div>
        </div>

        <DataTable table={table} emptyMessage={counts.all ? 'No timesheets match.' : 'No timesheets yet.'} />

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
