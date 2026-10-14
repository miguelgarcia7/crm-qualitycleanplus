import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import StatusFilter from '@/components/table/StatusFilter'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, router } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, Row as TableRow, useReactTable } from '@tanstack/react-table'
import { useEffect, useMemo, useRef, useState } from 'react'

type Visit = {
  id: number
  recruiter: string | null
  recruiter_id: number
  property: string | null
  property_id: number
  date: string
  in: string
  out: string | null
  minutes: number | null
  /** Hours since check-in while the visit is still open. */
  open_hours: number | null
  off_site: boolean
  late: boolean
  covering: boolean
}

type Filters = {
  flag: string
  search: string
  range: string
  from: string | null
  to: string | null
  recruiter_id: number | null
  property_id: number | null
  per_page: number
}

type Option = { id: number; name: string }

type Props = {
  visits: Visit[]
  pagination: PaginationMeta
  filters: Filters
  counts: Record<string, number>
  flags: { key: string; label: string }[]
  ranges: { value: string; label: string }[]
  recruiters: Option[]
  properties: Option[]
  can: { viewAll: boolean }
}

const fmtMinutes = (m: number) => (m >= 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m} min`)

/** Drop defaults so the URL carries only what is actually filtering. */
const queryFrom = (f: Filters, page: number): Record<string, string> => {
  const params: Record<string, string> = {}
  if (f.flag) params.flag = f.flag
  if (f.search) params.search = f.search
  if (f.range !== '30') params.range = f.range
  if (f.range === 'custom' && f.from) params.from = f.from
  if (f.range === 'custom' && f.to) params.to = f.to
  if (f.recruiter_id) params.recruiter_id = String(f.recruiter_id)
  if (f.property_id) params.property_id = String(f.property_id)
  if (f.per_page !== 25) params.per_page = String(f.per_page)
  if (page > 1) params.page = String(page)
  return params
}

const columnHelper = createColumnHelper<Visit>()

const Page = ({ visits, pagination, filters, counts, flags, ranges, recruiters, properties, can }: Props) => {
  const [search, setSearch] = useState(filters.search)
  // "Pick dates…" shows the two date boxes before anything is sent.
  const [picking, setPicking] = useState(filters.range === 'custom')

  const visit = (changes: Partial<Filters>, page = 1, replace = false) =>
    router.get('/admin/field-visits', queryFrom({ ...filters, ...changes }, page), {
      preserveState: true,
      preserveScroll: true,
      replace,
      only: ['visits', 'pagination', 'filters', 'counts'],
    })

  // Debounced so typing doesn't fire a request per keystroke.
  const typed = useRef(false)
  useEffect(() => {
    if (!typed.current || search === filters.search) return
    const timer = setTimeout(() => visit({ search }, 1, true), 300)
    return () => clearTimeout(timer)
  }, [search]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => setSearch(filters.search), [filters.search])

  // Open visits ignore the date range: one left open for weeks is the one to find.
  const anyDate = filters.flag === 'open' || filters.flag === 'stale'

  const columns = useMemo(
    () => [
      ...(can.viewAll
        ? [
            columnHelper.accessor('recruiter', {
              header: 'Recruiter',
              enableSorting: false,
              cell: ({ row }: { row: TableRow<Visit> }) => (
                <Link href={`/admin/people/${row.original.recruiter_id}`} className="text-default-900 hover:text-primary font-semibold whitespace-nowrap">
                  {row.original.recruiter}
                </Link>
              ),
            }),
          ]
        : []),
      columnHelper.accessor('property', {
        header: 'Hotel',
        enableSorting: false,
        cell: ({ row }) => (
          <Link href={`/admin/properties/${row.original.property_id}#visits`} className="hover:text-primary whitespace-nowrap">
            {row.original.property}
          </Link>
        ),
      }),
      columnHelper.accessor('date', { header: 'Date', enableSorting: false, cell: ({ row }) => <span className="whitespace-nowrap">{row.original.date}</span> }),
      columnHelper.accessor('in', { header: 'Checked in', enableSorting: false, cell: ({ row }) => <span className="whitespace-nowrap tabular-nums">{row.original.in}</span> }),
      columnHelper.accessor('out', {
        header: 'Checked out',
        enableSorting: false,
        cell: ({ row }) =>
          row.original.out ? (
            <span className="whitespace-nowrap tabular-nums">{row.original.out}</span>
          ) : (
            <span className="badge badge-label bg-danger/15 text-danger whitespace-nowrap">Open {row.original.open_hours ?? 0} h</span>
          ),
      }),
      columnHelper.accessor('minutes', {
        header: 'Time on site',
        enableSorting: false,
        cell: ({ row }) => <span className="whitespace-nowrap tabular-nums">{row.original.minutes === null ? '—' : fmtMinutes(row.original.minutes)}</span>,
      }),
      {
        id: 'flags',
        header: 'Flags',
        enableSorting: false,
        cell: ({ row }: { row: TableRow<Visit> }) => {
          const v = row.original
          return v.off_site || v.late || v.covering ? (
            <div className="flex flex-wrap gap-1">
              {v.off_site && <span className="badge badge-label bg-warning/15 text-warning">Off-site</span>}
              {v.late && <span className="badge badge-label bg-secondary/15 text-secondary">Closed late</span>}
              {v.covering && <span className="badge badge-label bg-info/15 text-info">Covering</span>}
            </div>
          ) : (
            <span className="text-default-500">—</span>
          )
        },
      },
    ],
    [can.viewAll],
  )

  const table = useReactTable({
    data: visits,
    columns,
    // The database filters and slices; the table only renders what it is given.
    manualPagination: true,
    pageCount: pagination.last_page,
    getCoreRowModel: getCoreRowModel(),
  })

  const exportQuery = new URLSearchParams(queryFrom(filters, 1)).toString()

  return (
    <>
      <Head title="Field Visits" />
      <PageBreadcrumb title="Field Visits" subtitle="Operations" />

      <div className="card">
        <div className="card-header flex-col items-stretch gap-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <StatusFilter
              label="Show visits"
              options={flags.map((f) => ({ key: f.key, label: f.label, count: counts[f.key] }))}
              value={filters.flag}
              onChange={(flag) => visit({ flag })}
            />
            <a href={`/admin/field-visits/export${exportQuery ? `?${exportQuery}` : ''}`} className="btn border-default-300 hover:border-default-400 border text-nowrap">
              <Icon icon="download" className="size-4" /> Export Excel
            </a>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <div className="input-icon-group min-w-56 flex-1">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder={can.viewAll ? 'Search recruiter or hotel…' : 'Search hotel…'}
                aria-label="Search visits"
                value={search}
                onChange={(e) => {
                  typed.current = true
                  setSearch(e.target.value)
                }}
              />
            </div>

            <select
              className="form-select w-auto"
              aria-label="When"
              disabled={anyDate}
              title={anyDate ? 'Open visits show whatever their date' : undefined}
              value={anyDate ? 'all' : picking ? 'custom' : filters.range}
              onChange={(e) => {
                if (e.target.value === 'custom') return setPicking(true)
                setPicking(false)
                visit({ range: e.target.value, from: null, to: null })
              }}
            >
              {ranges.map((r) => (
                <option key={r.value} value={r.value}>
                  {anyDate && r.value === 'all' ? 'Any date' : r.label}
                </option>
              ))}
            </select>

            {picking && !anyDate && (
              <div className="flex flex-wrap items-center gap-2">
                <input
                  type="date"
                  className="form-input w-auto"
                  aria-label="From"
                  value={filters.from ?? ''}
                  onChange={(e) => visit({ range: 'custom', from: e.target.value || null })}
                />
                <span className="text-default-500">to</span>
                <input
                  type="date"
                  className="form-input w-auto"
                  aria-label="To"
                  value={filters.to ?? ''}
                  onChange={(e) => visit({ range: 'custom', to: e.target.value || null })}
                />
              </div>
            )}

            {can.viewAll && recruiters.length > 1 && (
              <select
                className="form-select w-auto"
                aria-label="Recruiter"
                value={filters.recruiter_id ?? ''}
                onChange={(e) => visit({ recruiter_id: e.target.value ? Number(e.target.value) : null })}
              >
                <option value="">All recruiters</option>
                {recruiters.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            )}

            {properties.length > 1 && (
              <select
                className="form-select w-auto"
                aria-label="Hotel"
                value={filters.property_id ?? ''}
                onChange={(e) => visit({ property_id: e.target.value ? Number(e.target.value) : null })}
              >
                <option value="">All hotels</option>
                {properties.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            )}

            <select className="form-select ms-auto w-20" aria-label="Rows per page" value={filters.per_page} onChange={(e) => visit({ per_page: Number(e.target.value) })}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage={counts[''] ? 'No visits match.' : 'No visits yet.'} />

        {pagination.total > 0 && (
          <div className="card-footer">
            <ServerPagination meta={pagination} itemsName="visits" onPageChange={(page) => visit({}, page)} />
          </div>
        )}
      </div>
      <p className="text-default-500 mt-3 text-sm">Times are each hotel&apos;s own.{can.viewAll ? '' : ' You see your own visits.'}</p>
    </>
  )
}

export default Page
