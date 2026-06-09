import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
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

type Posting = {
  id: number
  slug: string
  title: string
  status: string
  status_label: string
  pay_range: string | null
  content: string | null
  hour_start: string | null
  hour_end: string | null
  property_id: number | null
  location_label: string | null
  location: string | null
  applications_count: number
}

type Props = {
  postings: Posting[]
  properties: { id: number; name: string }[]
}

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  published: 'bg-success/15 text-success',
  closed: 'bg-secondary/15 text-secondary',
}

const emptyForm = {
  title: '',
  pay_range: '',
  content: '',
  hour_start: '',
  hour_end: '',
  property_id: '',
  location_label: '',
}

const columnHelper = createColumnHelper<Posting>()

const Page = ({ postings, properties }: Props) => {
  const [editing, setEditing] = useState<Posting | null>(null)
  const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm(emptyForm)

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const startEdit = (p: Posting) => {
    setEditing(p)
    clearErrors()
    setData({
      title: p.title,
      pay_range: p.pay_range ?? '',
      content: p.content ?? '',
      hour_start: p.hour_start ?? '',
      hour_end: p.hour_end ?? '',
      property_id: p.property_id ? String(p.property_id) : '',
      location_label: p.location_label ?? '',
    })
  }

  const cancelEdit = () => {
    setEditing(null)
    clearErrors()
    reset()
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      put(`/admin/job-postings/${editing.slug}`, { preserveScroll: true, onSuccess: cancelEdit })
    } else {
      post('/admin/job-postings', { preserveScroll: true, onSuccess: () => reset() })
    }
  }

  const publish = (p: Posting) => router.post(`/admin/job-postings/${p.slug}/publish`, {}, { preserveScroll: true })
  const close = (p: Posting) => router.post(`/admin/job-postings/${p.slug}/close`, {}, { preserveScroll: true })
  const destroy = (p: Posting) => {
    if (confirm(`Delete "${p.title}"? This only works while it has no applications.`)) {
      router.delete(`/admin/job-postings/${p.slug}`, { preserveScroll: true })
    }
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('title', {
        header: 'Title',
        cell: ({ row }) => <span className="font-semibold">{row.original.title}</span>,
      }),
      columnHelper.accessor('location', {
        header: 'Location',
        cell: ({ row }) => row.original.location ?? '—',
      }),
      columnHelper.accessor('pay_range', {
        header: 'Pay',
        cell: ({ row }) => row.original.pay_range ?? '—',
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => (
          <span className={cn('badge badge-label', statusBadge[row.original.status] ?? 'bg-light text-default-600')}>
            {row.original.status_label}
          </span>
        ),
      }),
      columnHelper.accessor('applications_count', {
        header: 'Apps',
        cell: ({ row }) => (
          <Link href={`/admin/applicants`} className={row.original.applications_count > 0 ? 'text-primary font-semibold' : ''}>
            {row.original.applications_count}
          </Link>
        ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Posting> }) => {
          const p = row.original
          return (
            <div className="flex justify-center gap-1.5">
              {p.status !== 'published' ? (
                <button className="btn btn-sm btn-soft-success" onClick={() => publish(p)}>
                  Publish
                </button>
              ) : (
                <button className="btn btn-sm btn-soft-secondary" onClick={() => close(p)}>
                  Close
                </button>
              )}
              <button
                className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
                onClick={() => startEdit(p)}
                title="Edit posting"
              >
                <Icon icon="edit" className="text-base" />
              </button>
              {p.applications_count === 0 && (
                <button
                  className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
                  onClick={() => destroy(p)}
                  title="Delete posting"
                >
                  <Icon icon="trash" className="text-base" />
                </button>
              )}
            </div>
          )
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  )

  const table = useReactTable({
    data: postings,
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
      <Head title="Job Postings" />
      <PageBreadcrumb title="Job Postings" subtitle="Recruiting" />

      <div className="gap-base grid lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="card">
            <div className="card-header">
              <div className="flex flex-wrap gap-3">
                <div className="input-icon-group">
                  <Icon icon="search" className="input-icon" />
                  <input
                    className="form-input"
                    placeholder="Search postings..."
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
                  <option value="published">Published</option>
                  <option value="closed">Closed</option>
                </select>
                <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
                  {[10, 25, 50].map((size) => (
                    <option key={size}>{size}</option>
                  ))}
                </select>
              </div>
            </div>

            <DataTable table={table} emptyMessage="No postings yet. Create one and publish it to the public job board." />

            {table.getRowModel().rows.length > 0 && (
              <div className="card-footer">
                <TablePagination
                  totalItems={totalItems}
                  start={start}
                  end={end}
                  itemsName="postings"
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
          <p className="text-default-400 mt-3 text-xs">
            Published postings appear on the public{' '}
            <Link href="/job-openings" className="text-primary">
              job board
            </Link>
            ; applicants land in the Applicants queue.
          </p>
        </div>

        <div>
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">{editing ? `Edit "${editing.title}"` : 'New posting'}</h4>
            </div>
            <div className="card-body">
              <form onSubmit={submit} className="space-y-3">
                <div>
                  <label className="form-label">Title</label>
                  <input className="form-input w-full" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                  {errors.title && <p className="text-danger text-sm">{errors.title}</p>}
                </div>
                <div>
                  <label className="form-label">Property (optional)</label>
                  <select className="form-select w-full" value={data.property_id} onChange={(e) => setData('property_id', e.target.value)}>
                    <option value="">No linked property</option>
                    {properties.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="form-label">Location label (when no property)</label>
                  <input
                    className="form-input w-full"
                    value={data.location_label}
                    onChange={(e) => setData('location_label', e.target.value)}
                    placeholder="Dallas, TX"
                  />
                </div>
                <div>
                  <label className="form-label">Pay range</label>
                  <input
                    className="form-input w-full"
                    value={data.pay_range}
                    onChange={(e) => setData('pay_range', e.target.value)}
                    placeholder="$16 - $18 / hr"
                  />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="form-label">Shift start</label>
                    <input
                      type="time"
                      className="form-input w-full"
                      value={data.hour_start}
                      onChange={(e) => setData('hour_start', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="form-label">Shift end</label>
                    <input type="time" className="form-input w-full" value={data.hour_end} onChange={(e) => setData('hour_end', e.target.value)} />
                  </div>
                </div>
                <div>
                  <label className="form-label">Description</label>
                  <textarea className="form-input w-full" rows={4} value={data.content} onChange={(e) => setData('content', e.target.value)} />
                </div>
                <div className="flex gap-2">
                  <button className="btn bg-primary hover:bg-primary-hover flex-1 py-2 font-semibold text-white" disabled={processing}>
                    {editing ? 'Save changes' : 'Create draft'}
                  </button>
                  {editing && (
                    <button type="button" className="btn btn-light" onClick={cancelEdit}>
                      Cancel
                    </button>
                  )}
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
