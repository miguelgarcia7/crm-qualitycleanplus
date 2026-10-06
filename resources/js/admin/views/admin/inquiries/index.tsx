import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router } from '@inertiajs/react'
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
import { ReactNode, useEffect, useMemo, useState } from 'react'

type Inquiry = {
  id: number
  type: 'job_seeker' | 'business'
  name: string
  email: string
  phone: string | null
  company: string | null
  address: string | null
  city: string | null
  state: string | null
  zip: string | null
  inquiry_type: string | null
  call_back_time: string | null
  message: string | null
  spanish: boolean
  spam_check: string | null
  unverified: boolean
  received_at: string | null
  handled_at: string | null
  handled_by: string | null
}

type Props = {
  inquiries: Inquiry[]
  open: number | null
}

const TYPE_LABEL: Record<Inquiry['type'], string> = { job_seeker: 'Job seeker', business: 'Business' }

// Times are stored in UTC; show them in the viewer's own time zone.
const formatDateTime = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—'

const TypeBadge = ({ inquiry }: { inquiry: Inquiry }) => (
  <span className="flex flex-wrap gap-1">
    <span className={cn('badge badge-label whitespace-nowrap', inquiry.type === 'business' ? 'bg-primary/15 text-primary' : 'bg-info/15 text-info')}>
      {TYPE_LABEL[inquiry.type]}
    </span>
    {inquiry.spanish && <span className="badge badge-label bg-secondary/15 text-secondary">Spanish</span>}
  </span>
)

const StatusBadge = ({ inquiry }: { inquiry: Inquiry }) => (
  <span className={cn('badge badge-label', inquiry.handled_at ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning')}>
    {inquiry.handled_at ? 'Handled' : 'New'}
  </span>
)

// preserveState keeps the page mounted, so a page opened from the lead email
// (?open=) doesn't pop its dialog open again after the redirect back.
const setHandled = (inquiry: Inquiry, handled: boolean, onSuccess?: () => void) =>
  router.patch(`/admin/inquiries/${inquiry.id}`, { handled }, { preserveScroll: true, preserveState: true, onSuccess })

const destroy = (inquiry: Inquiry, onSuccess?: () => void) =>
  confirmAction({
    title: 'Delete inquiry',
    message: (
      <>
        Delete the inquiry from <strong>{inquiry.name}</strong>? Use this for spam. To keep it but take it off the new list, mark it handled instead.
      </>
    ),
    onConfirm: () => router.delete(`/admin/inquiries/${inquiry.id}`, { preserveScroll: true, preserveState: true, onSuccess }),
  })

const Field = ({ label, children }: { label: string; children: ReactNode }) => (
  <div>
    <dt className="text-default-400 text-xs">{label}</dt>
    <dd className="break-words">{children}</dd>
  </div>
)

/** Everything the visitor sent, with reply links and the handled toggle. */
const InquiryModal = ({ inquiry, onClose }: { inquiry: Inquiry; onClose: () => void }) => {
  // Escape closes, like the app's other dialogs.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const location = [inquiry.city, [inquiry.state, inquiry.zip].filter(Boolean).join(' ')].filter(Boolean).join(', ')

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="inquiry-modal-title"
      onClick={onClose}>
      <div className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <div>
            <h4 className="card-title" id="inquiry-modal-title">
              {inquiry.name}
              {inquiry.company && <span className="text-default-400 font-normal"> · {inquiry.company}</span>}
            </h4>
            <p className="text-default-400 text-xs">Received {formatDateTime(inquiry.received_at)}</p>
          </div>
          <button type="button" onClick={onClose} aria-label="Close">
            <Icon icon="x" className="size-5" />
          </button>
        </div>

        <div className="card-body max-h-[70vh] space-y-4 overflow-y-auto">
          <div className="flex flex-wrap items-center gap-2">
            <TypeBadge inquiry={inquiry} />
            <StatusBadge inquiry={inquiry} />
          </div>

          {inquiry.spanish && <p className="text-default-500 text-sm">Sent from the Spanish site — they may prefer a reply in Spanish.</p>}

          <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Field label="Email">
              <a href={`mailto:${inquiry.email}`} className="text-primary">
                {inquiry.email}
              </a>
            </Field>
            <Field label="Phone">
              {inquiry.phone ? (
                <a href={`tel:${inquiry.phone}`} className="text-primary">
                  {inquiry.phone}
                </a>
              ) : (
                '—'
              )}
            </Field>
            {inquiry.call_back_time && <Field label="Best time to call">{inquiry.call_back_time}</Field>}
            {inquiry.inquiry_type && <Field label="Inquiry type">{inquiry.inquiry_type}</Field>}
            {inquiry.address && <Field label="Address">{inquiry.address}</Field>}
            {location && <Field label="City / State / Zip">{location}</Field>}
          </dl>

          <Field label="Message">
            {inquiry.message ? <p className="whitespace-pre-line">{inquiry.message}</p> : <span className="text-default-400">No message.</span>}
          </Field>

          <div className="text-default-400 space-y-1 border-t border-dashed border-default-200 pt-3 text-xs">
            {inquiry.spam_check && (
              <p className={cn(inquiry.unverified && 'text-warning')}>
                <Icon icon={inquiry.unverified ? 'alert-triangle' : 'shield-check'} className="me-1 inline" />
                Spam check: {inquiry.spam_check}
              </p>
            )}
            {inquiry.handled_at && (
              <p>
                Handled {formatDateTime(inquiry.handled_at)}
                {inquiry.handled_by && <> by {inquiry.handled_by}</>}
              </p>
            )}
          </div>
        </div>

        <div className="card-footer flex flex-wrap justify-between gap-2">
          <button type="button" className="btn btn-light" onClick={() => destroy(inquiry, onClose)}>
            <Icon icon="trash" />
            Delete
          </button>
          <div className="flex gap-2">
            <a href={`mailto:${inquiry.email}`} className="btn btn-light">
              <Icon icon="mail" />
              Reply
            </a>
            {inquiry.handled_at ? (
              <button type="button" className="btn btn-light" onClick={() => setHandled(inquiry, false, onClose)}>
                Mark as new
              </button>
            ) : (
              <button type="button" className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => setHandled(inquiry, true, onClose)}>
                <Icon icon="check" />
                Mark handled
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

const columnHelper = createColumnHelper<Inquiry>()

const Page = ({ inquiries, open }: Props) => {
  // The lead email links here with ?open={id}; a deleted one just opens nothing.
  const [viewingId, setViewingId] = useState<number | null>(open)
  const viewing = inquiries.find((i) => i.id === viewingId) ?? null

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([{ id: 'received_at', desc: true }])
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 25 })

  const newCount = inquiries.filter((i) => !i.handled_at).length

  const columns = useMemo(
    () => [
      columnHelper.accessor('received_at', {
        header: 'Received',
        sortUndefined: 'last',
        cell: ({ row }) => <span className="whitespace-nowrap">{formatDateTime(row.original.received_at)}</span>,
      }),
      columnHelper.accessor('name', {
        header: 'From',
        cell: ({ row }) => (
          <button type="button" className="text-start" onClick={() => setViewingId(row.original.id)}>
            <p className={cn('font-semibold', !row.original.handled_at && 'text-default-800')}>{row.original.name}</p>
            <p className="text-default-400 text-xs">{row.original.company ?? row.original.email}</p>
          </button>
        ),
      }),
      columnHelper.accessor('type', {
        header: 'Form',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => <TypeBadge inquiry={row.original} />,
      }),
      columnHelper.accessor('message', {
        header: 'Message',
        enableSorting: false,
        cell: ({ row }) => (
          <p className="text-default-500 line-clamp-2 max-w-md whitespace-normal">
            {row.original.inquiry_type && <span className="text-default-700">{row.original.inquiry_type}. </span>}
            {row.original.message ?? ''}
          </p>
        ),
      }),
      columnHelper.accessor((i) => (i.handled_at ? 'handled' : 'new'), {
        id: 'status',
        header: 'Status',
        filterFn: 'equalsString',
        enableColumnFilter: true,
        cell: ({ row }) => (
          <span className="flex items-center gap-1.5">
            <StatusBadge inquiry={row.original} />
            {row.original.unverified && (
              <span title={`Spam check: ${row.original.spam_check}`}>
                <Icon icon="alert-triangle" className="text-warning" />
              </span>
            )}
          </span>
        ),
      }),
      // Search covers these too, without showing them as columns.
      columnHelper.accessor((i) => [i.email, i.phone, i.company].filter(Boolean).join(' '), { id: 'contact' }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Inquiry> }) => {
          const i = row.original
          return (
            <div className="flex justify-end gap-1.5">
              <button className="btn btn-icon border-default-300 hover:border-default-400 border" onClick={() => setViewingId(i.id)} title="View inquiry">
                <Icon icon="eye" className="text-base" />
              </button>
              <button
                className="btn btn-icon border-default-300 hover:border-default-400 border"
                onClick={() => setHandled(i, !i.handled_at)}
                title={i.handled_at ? 'Mark as new' : 'Mark handled'}
              >
                <Icon icon={i.handled_at ? 'arrow-back-up' : 'check'} className="text-base" />
              </button>
              <button className="btn btn-icon border-default-300 hover:border-default-400 border" onClick={() => destroy(i)} title="Delete inquiry">
                <Icon icon="trash" className="text-base" />
              </button>
            </div>
          )
        },
      },
    ],
    [],
  )

  const table = useReactTable({
    data: inquiries,
    columns,
    state: { sorting, globalFilter, columnFilters, pagination, columnVisibility: { contact: false } },
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

  const filterSelect = (column: string, placeholder: string, options: [string, string][]) => (
    <select
      className="form-select w-auto"
      value={(table.getColumn(column)?.getFilterValue() as string) ?? 'All'}
      onChange={(e) => table.getColumn(column)?.setFilterValue(e.target.value === 'All' ? undefined : e.target.value)}
    >
      <option value="All">{placeholder}</option>
      {options.map(([value, label]) => (
        <option key={value} value={value}>
          {label}
        </option>
      ))}
    </select>
  )

  return (
    <>
      <Head title="Contact Inquiries" />
      <PageBreadcrumb title="Contact Inquiries" subtitle="Website" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap items-center gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input className="form-input" placeholder="Search inquiries..." value={globalFilter} onChange={(e) => setGlobalFilter(e.target.value)} />
            </div>
            <span className="text-default-500 text-sm">{newCount === 0 ? 'Nothing new' : `${newCount} new`}</span>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="me-1 font-semibold text-nowrap">Filter By:</span>
            {filterSelect('type', 'Form', [
              ['job_seeker', 'Job seeker'],
              ['business', 'Business'],
            ])}
            {filterSelect('status', 'Status', [
              ['new', 'New'],
              ['handled', 'Handled'],
            ])}
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No inquiries yet. Leads from the website's contact forms show up here." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="inquiries"
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
        Every inquiry is also emailed to the form&apos;s recipients. Mark one handled once someone has followed up.
      </p>

      {viewing && <InquiryModal key={viewing.id} inquiry={viewing} onClose={() => setViewingId(null)} />}
    </>
  )
}

export default Page
