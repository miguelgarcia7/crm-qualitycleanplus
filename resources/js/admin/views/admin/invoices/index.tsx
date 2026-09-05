import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, Row as TableRow, SortingState, useReactTable } from '@tanstack/react-table'
import { useEffect, useMemo, useRef, useState } from 'react'

type Row = { id: number; invoice_number: string; property: string | null; issue_date: string; total: number; status: string; status_label: string }
type Filters = {
  search: string
  status: string
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
  properties: { id: number; name: string }[]
}

/** Drop empties so the URL carries only what is actually filtering. */
const queryFrom = (filters: Filters, page: number): Record<string, string> => {
  const params: Record<string, string> = {}
  if (filters.search !== '') params.search = filters.search
  if (filters.status !== '') params.status = filters.status
  if (filters.property_id) params.property_id = String(filters.property_id)
  if (filters.sort !== 'issue_date') params.sort = filters.sort
  if (filters.direction !== 'desc') params.direction = filters.direction
  if (filters.per_page !== 25) params.per_page = String(filters.per_page)
  if (page > 1) params.page = String(page)
  return params
}

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  invoiced: 'bg-info/15 text-info',
  invoice_sent: 'bg-success/15 text-success',
  voided: 'bg-danger/15 text-danger',
}

const columnHelper = createColumnHelper<Row>()

const Page = ({ invoices, pagination, filters, statuses, properties }: Props) => {
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
              <option value="">Status</option>
              {statuses.map((status) => (
                <option key={status.value} value={status.value}>
                  {status.label}
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

        <DataTable table={table} emptyMessage="No invoices yet." />

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
