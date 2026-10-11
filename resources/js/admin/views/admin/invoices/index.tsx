import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, Row as TableRow, SortingState, useReactTable } from '@tanstack/react-table'
import { useEffect, useMemo, useRef, useState } from 'react'

type Row = {
  id: number
  invoice_number: string
  property_id: number
  property: string | null
  week_start: string | null
  week_end: string | null
  issue_date: string
  due_date: string
  total: number
  status: string
  status_label: string
  paid_at: string | null
  is_overdue: boolean
  days_overdue: number | null
}
type Filters = {
  search: string
  status: string
  payment: string
  property_id: number | null
  sort: string
  direction: string
  per_page: number
}
type Props = {
  invoices: Row[]
  pagination: PaginationMeta
  filters: Filters
  statuses: { value: string; label: string }[]
  paymentStates: string[]
  properties: { id: number; name: string }[]
}

/** Drop empties so the URL carries only what is actually filtering. */
const queryFrom = (filters: Filters, page: number): Record<string, string> => {
  const params: Record<string, string> = {}
  if (filters.search !== '') params.search = filters.search
  if (filters.status !== '') params.status = filters.status
  if (filters.payment !== '') params.payment = filters.payment
  if (filters.property_id) params.property_id = String(filters.property_id)
  if (filters.sort !== 'issue_date') params.sort = filters.sort
  if (filters.direction !== 'desc') params.direction = filters.direction
  if (filters.per_page !== 25) params.per_page = String(filters.per_page)
  if (page > 1) params.page = String(page)
  return params
}

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
// Noon, so no timezone offset can push the date to a neighbouring day.
const at = (iso: string) => new Date(`${iso}T12:00:00`)
const longDate = (iso: string) => at(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
/** "Oct 5 – 11", or "Sep 28 – Oct 4" across a month; the year only when it isn't this one. */
const weekLabel = (start: string, end: string) => {
  const s = at(start)
  const e = at(end)
  const month = (d: Date) => d.toLocaleDateString('en-US', { month: 'short' })
  const range = s.getMonth() === e.getMonth() ? `${month(s)} ${s.getDate()} – ${e.getDate()}` : `${month(s)} ${s.getDate()} – ${month(e)} ${e.getDate()}`
  return e.getFullYear() === new Date().getFullYear() ? range : `${range}, ${e.getFullYear()}`
}

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  invoiced: 'bg-info/15 text-info',
  invoice_sent: 'bg-success/15 text-success',
  voided: 'bg-danger/15 text-danger',
}

const columnHelper = createColumnHelper<Row>()

const Page = ({ invoices, pagination, filters, statuses, paymentStates, properties }: Props) => {
  const [search, setSearch] = useState(filters.search)

  /**
   * Every control routes through here: the query string is the state, so a
   * filtered view is a link someone can send and Back steps through filters.
   */
  const visit = (changes: Partial<Filters>, page = 1, replace = false) => {
    router.get('/admin/invoices', queryFrom({ ...filters, ...changes }, page), {
      preserveState: true,
      preserveScroll: true,
      replace,
      only: ['invoices', 'pagination', 'filters'],
    })
  }

  // Debounced so typing does not fire a request per keystroke; replaces
  // history rather than pushing, so Back does not walk it letter by letter.
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
        cell: ({ row }) =>
          row.original.property ? (
            <Link href={`/admin/properties/${row.original.property_id}`} className="hover:text-primary">
              {row.original.property}
            </Link>
          ) : (
            '—'
          ),
      }),
      columnHelper.accessor('week_start', {
        header: 'Week',
        enableSorting: false,
        cell: ({ row }) =>
          row.original.week_start && row.original.week_end ? (
            <span className="whitespace-nowrap">{weekLabel(row.original.week_start, row.original.week_end)}</span>
          ) : (
            '—'
          ),
      }),
      columnHelper.accessor('issue_date', {
        header: 'Issued',
        cell: ({ row }) => <span className="whitespace-nowrap">{longDate(row.original.issue_date)}</span>,
      }),
      columnHelper.accessor('due_date', {
        header: 'Due',
        cell: ({ row }) => <span className="whitespace-nowrap">{longDate(row.original.due_date)}</span>,
      }),
      columnHelper.accessor('total', {
        header: 'Total',
        meta: { align: 'end' },
        cell: ({ row }) => <span className="tabular-nums">{money(row.original.total)}</span>,
      }),
      columnHelper.accessor('paid_at', {
        header: 'Payment',
        enableSorting: false,
        cell: ({ row }) => {
          const i = row.original
          if (i.paid_at) return <span className="badge badge-label bg-success/15 text-success whitespace-nowrap">Paid {longDate(i.paid_at)}</span>
          if (i.is_overdue)
            return (
              <span className="badge badge-label bg-danger/15 text-danger whitespace-nowrap">
                Overdue{i.days_overdue ? ` · ${i.days_overdue} ${i.days_overdue === 1 ? 'day' : 'days'}` : ''}
              </span>
            )
          return <span className="text-default-400">—</span>
        },
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ row }) => (
          <span className={cn('badge badge-label', statusBadge[row.original.status] ?? 'bg-secondary/15 text-secondary')}>
            {row.original.status_label}
          </span>
        ),
      }),
      {
        header: 'Actions',
        enableSorting: false,
        cell: ({ row }: { row: TableRow<Row> }) => (
          <div className="flex justify-center gap-1.5">
            <a
              href={`/admin/invoices/${row.original.id}/pdf`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="Download PDF"
              aria-label={`Download ${row.original.invoice_number} as a PDF`}>
              <Icon icon="download" className="text-base" />
            </a>
            <Link
              href={`/admin/invoices/${row.original.id}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="View invoice"
              aria-label={`View ${row.original.invoice_number}`}>
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
    state: { sorting },
    // The database orders and slices; the table only renders what it is given.
    manualSorting: true,
    manualPagination: true,
    pageCount: pagination.last_page,
    onSortingChange: (updater) => {
      const next = typeof updater === 'function' ? updater(sorting) : updater
      const [first] = next
      visit(first ? { sort: first.id, direction: first.desc ? 'desc' : 'asc' } : { sort: 'issue_date', direction: 'desc' })
    },
    getCoreRowModel: getCoreRowModel(),
  })

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
                placeholder="Search invoice # or property..."
                value={search}
                onChange={(e) => {
                  typed.current = true
                  setSearch(e.target.value)
                }}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="me-1 font-semibold text-nowrap">Filter By:</span>
            <select className="form-select w-auto" value={filters.status} onChange={(e) => visit({ status: e.target.value })}>
              <option value="">All statuses</option>
              {statuses.map((status) => (
                <option key={status.value} value={status.value}>
                  {status.label}
                </option>
              ))}
            </select>
            <select className="form-select w-auto" value={filters.payment} onChange={(e) => visit({ payment: e.target.value })}>
              <option value="">All payments</option>
              {paymentStates.map((state) => (
                <option key={state} value={state} className="capitalize">
                  {state[0].toUpperCase() + state.slice(1)}
                </option>
              ))}
            </select>
            {properties.length > 1 && (
              <select
                className="form-select w-auto min-w-40"
                value={filters.property_id ?? ''}
                onChange={(e) => visit({ property_id: e.target.value === '' ? null : Number(e.target.value) })}>
                <option value="">All properties</option>
                {properties.map((property) => (
                  <option key={property.id} value={property.id}>
                    {property.name}
                  </option>
                ))}
              </select>
            )}
            <select className="form-select w-20" value={filters.per_page} onChange={(e) => visit({ per_page: Number(e.target.value) })}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable
          table={table}
          emptyMessage={filters.search || filters.status || filters.payment || filters.property_id ? 'No invoices match these filters.' : 'No invoices yet.'}
        />

        {pagination.total > 0 && (
          <div className="card-footer">
            <ServerPagination meta={pagination} itemsName="invoices" onPageChange={(page) => visit({}, page)} />
          </div>
        )}
      </div>
    </>
  )
}

export default Page
