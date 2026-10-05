import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router, useForm } from '@inertiajs/react'
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
import { useEffect, useMemo, useState } from 'react'

type Testimonial = {
  id: number
  name: string
  quote: string
  source: string
  source_label: string
  rating: number
  is_active: boolean
  photo_url: string | null
  created_at: string | null
  created_at_display: string | null
}

type Source = { value: string; label: string }

type Props = {
  testimonials: Testimonial[]
  sources: Source[]
}

type FormData = {
  name: string
  quote: string
  source: string
  rating: number
  is_active: boolean
  photo: File | null
  remove_photo: boolean
}

const Stars = ({ rating }: { rating: number }) => (
  <span className="text-warning whitespace-nowrap" title={`${rating} of 5`}>
    {'★'.repeat(rating)}
    <span className="text-default-300">{'★'.repeat(5 - rating)}</span>
  </span>
)

/** Add or edit one testimonial in a dialog. `editing` null = a new one. */
const TestimonialModal = ({ editing, sources, onClose }: { editing: Testimonial | null; sources: Source[]; onClose: () => void }) => {
  const { data, setData, post, processing, errors, transform } = useForm<FormData>({
    name: editing?.name ?? '',
    quote: editing?.quote ?? '',
    source: editing?.source ?? 'google',
    rating: editing?.rating ?? 5,
    is_active: editing?.is_active ?? true,
    photo: null,
    remove_photo: false,
  })

  // Escape closes, like the app's other dialogs.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    // Multipart (the photo) can't go as a real PUT, so updates post with a
    // method override.
    transform((d) => ({ ...d, is_active: d.is_active ? 1 : 0, remove_photo: d.remove_photo ? 1 : 0, ...(editing ? { _method: 'put' } : {}) }))
    post(editing ? `/admin/testimonials/${editing.id}` : '/admin/testimonials', { forceFormData: true, preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="testimonial-modal-title"
      onClick={onClose}>
      <form className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
        <div className="card-header">
          <h4 className="card-title" id="testimonial-modal-title">
            {editing ? `Edit testimonial from ${editing.name}` : 'New testimonial'}
          </h4>
          <button type="button" onClick={onClose} aria-label="Close">
            <Icon icon="x" className="size-5" />
          </button>
        </div>

        <div className="card-body max-h-[75vh] space-y-3 overflow-y-auto">
          <div>
            <label className="form-label">Name</label>
            <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} maxLength={120} required autoFocus />
            {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
          </div>
          <div>
            <label className="form-label">Quote</label>
            <textarea className="form-textarea" rows={4} value={data.quote} onChange={(e) => setData('quote', e.target.value)} maxLength={1000} required />
            {errors.quote && <p className="text-danger text-sm">{errors.quote}</p>}
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="form-label">Left on</label>
              <select className="form-select w-full" value={data.source} onChange={(e) => setData('source', e.target.value)}>
                {sources.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </select>
              {errors.source && <p className="text-danger text-sm">{errors.source}</p>}
            </div>
            <div>
              <label className="form-label">Rating</label>
              <select className="form-select w-full" value={data.rating} onChange={(e) => setData('rating', Number(e.target.value))}>
                {[5, 4, 3, 2, 1].map((r) => (
                  <option key={r} value={r}>
                    {r} {r === 1 ? 'star' : 'stars'}
                  </option>
                ))}
              </select>
              {errors.rating && <p className="text-danger text-sm">{errors.rating}</p>}
            </div>
          </div>
          <div>
            <label className="form-label">Photo (optional)</label>
            {editing?.photo_url && !data.remove_photo && !data.photo && (
              <div className="mb-2 flex items-center gap-3">
                <img src={editing.photo_url} alt="" className="size-12 rounded-full object-cover" />
                <button type="button" className="btn btn-sm btn-light" onClick={() => setData('remove_photo', true)}>
                  Remove photo
                </button>
              </div>
            )}
            <input
              type="file"
              accept="image/*"
              className="form-input w-full"
              onChange={(e) => setData((d) => ({ ...d, photo: e.target.files?.[0] ?? null, remove_photo: false }))}
            />
            {data.remove_photo && <p className="text-default-500 mt-1 text-xs">The photo will be removed when you save.</p>}
            {errors.photo && <p className="text-danger text-sm">{errors.photo}</p>}
          </div>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="form-checkbox size-4.5" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
            <span>Show on the website</span>
          </label>
        </div>

        <div className="card-footer flex justify-end gap-2">
          <button type="button" className="btn btn-light" onClick={onClose}>
            Cancel
          </button>
          <button className="btn bg-primary hover:bg-primary-hover text-white" disabled={processing}>
            {editing ? 'Save changes' : 'Add testimonial'}
          </button>
        </div>
      </form>
    </div>
  )
}

const columnHelper = createColumnHelper<Testimonial>()

const Page = ({ testimonials, sources }: Props) => {
  // null = closed; 'new' = adding; a testimonial = editing it.
  const [modal, setModal] = useState<Testimonial | 'new' | null>(null)

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([{ id: 'created_at', desc: true }])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const destroy = (t: Testimonial) => {
    confirmAction({
      title: 'Delete testimonial',
      message: (
        <>
          Delete the testimonial from <strong>{t.name}</strong>? To take it off the site but keep it, edit it and untick “Show on the website” instead.
        </>
      ),
      onConfirm: () => router.delete(`/admin/testimonials/${t.id}`, { preserveScroll: true }),
    })
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Person',
        cell: ({ row }) => (
          <div className="flex items-center gap-3">
            {row.original.photo_url ? (
              <img src={row.original.photo_url} alt="" className="size-9 shrink-0 rounded-full object-cover" />
            ) : (
              <span className="bg-default-100 text-default-500 flex size-9 shrink-0 items-center justify-center rounded-full">
                <Icon icon="user" />
              </span>
            )}
            <div>
              <p className="font-semibold">{row.original.name}</p>
              <p className="text-default-400 text-xs">{row.original.source_label}</p>
            </div>
          </div>
        ),
      }),
      columnHelper.accessor('quote', {
        header: 'Quote',
        enableSorting: false,
        cell: ({ row }) => <p className="line-clamp-2 max-w-xl whitespace-normal">{row.original.quote}</p>,
      }),
      columnHelper.accessor('rating', {
        header: 'Rating',
        cell: ({ row }) => <Stars rating={row.original.rating} />,
      }),
      columnHelper.accessor('created_at', {
        header: 'Created',
        sortUndefined: 'last',
        cell: ({ row }) => <span className="whitespace-nowrap">{row.original.created_at_display ?? '—'}</span>,
      }),
      columnHelper.accessor((t) => (t.is_active ? 'active' : 'hidden'), {
        id: 'status',
        header: 'Status',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => (
          <span className={cn('badge badge-label', row.original.is_active ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')}>
            {row.original.is_active ? 'On site' : 'Hidden'}
          </span>
        ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Testimonial> }) => {
          const t = row.original
          return (
            <div className="flex justify-end gap-1.5">
              <button
                className="btn btn-icon border-default-300 hover:border-default-400 border"
                onClick={() => setModal(t)}
                title="Edit testimonial"
              >
                <Icon icon="edit" className="text-base" />
              </button>
              <button
                className="btn btn-icon border-default-300 hover:border-default-400 border"
                onClick={() => destroy(t)}
                title="Delete testimonial"
              >
                <Icon icon="trash" className="text-base" />
              </button>
            </div>
          )
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  )

  const table = useReactTable({
    data: testimonials,
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
      <Head title="Testimonials" />
      <PageBreadcrumb title="Testimonials" subtitle="Website" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search testimonials..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>
            <button className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => setModal('new')}>
              <Icon icon="plus" />
              Add testimonial
            </button>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="me-1 font-semibold text-nowrap">Filter By:</span>
            <select
              className="form-select w-auto"
              value={(table.getColumn('status')?.getFilterValue() as string) ?? 'All'}
              onChange={(e) => table.getColumn('status')?.setFilterValue(e.target.value === 'All' ? undefined : e.target.value)}
            >
              <option value="All">Status</option>
              <option value="active">On site</option>
              <option value="hidden">Hidden</option>
            </select>
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No testimonials yet. Add one, or import them from the legacy site." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="testimonials"
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
      <p className="text-default-400 mt-3 text-xs">Testimonials marked “On site” show on the public home page, newest first.</p>

      {modal !== null && (
        <TestimonialModal key={modal === 'new' ? 'new' : modal.id} editing={modal === 'new' ? null : modal} sources={sources} onClose={() => setModal(null)} />
      )}
    </>
  )
}

export default Page
