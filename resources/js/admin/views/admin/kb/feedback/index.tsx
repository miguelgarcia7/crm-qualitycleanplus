import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import {
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

type Entry = {
  id: number
  type: string
  type_label: string
  is_vote: boolean
  message: string | null
  person: string
  article_title: string | null
  article_slug: string | null
  is_resolved: boolean
  resolved_by: string | null
  resolved_at: string | null
  admin_notes: string | null
  created_at: string | null
}

type Props = {
  entries: Entry[]
}

const TABS: { key: string; label: string }[] = [
  { key: 'open', label: 'Open' },
  { key: 'resolved', label: 'Resolved' },
  { key: 'votes', label: 'Votes' },
  { key: 'all', label: 'All' },
]

const typeBadge: Record<string, string> = {
  helpful: 'bg-success/15 text-success',
  not_helpful: 'bg-danger/15 text-danger',
  suggestion: 'bg-info/15 text-info',
  issue: 'bg-warning/15 text-warning',
  question: 'bg-primary/15 text-primary',
}

// Open = unresolved non-vote entries; votes live in their own tab.
const matchesTab = (e: Entry, tab: string) => {
  if (tab === 'all') return true
  if (tab === 'votes') return e.is_vote
  if (tab === 'open') return !e.is_vote && !e.is_resolved
  return !e.is_vote && e.is_resolved
}

const columnHelper = createColumnHelper<Entry>()

const Page = ({ entries }: Props) => {
  const [activeTab, setActiveTab] = useState('open')
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const counts = useMemo(() => {
    const result: Record<string, number> = {}
    for (const tab of TABS) {
      result[tab.key] = entries.filter((e) => matchesTab(e, tab.key)).length
    }
    return result
  }, [entries])

  const data = useMemo(() => entries.filter((e) => matchesTab(e, activeTab)), [entries, activeTab])

  const resolve = (e: Entry, resolved: boolean) =>
    router.put(`/admin/kb/feedback/${e.id}`, { is_resolved: resolved }, { preserveScroll: true })

  const destroy = (e: Entry) => {
    if (confirm('Delete this feedback entry?')) {
      router.delete(`/admin/kb/feedback/${e.id}`, { preserveScroll: true })
    }
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('type', {
        header: 'Type',
        cell: ({ row }) => (
          <span className={cn('badge badge-label', typeBadge[row.original.type] ?? 'bg-light text-default-600')}>
            {row.original.type_label}
          </span>
        ),
      }),
      columnHelper.accessor('article_title', {
        header: 'Article',
        cell: ({ row }) =>
          row.original.article_slug ? (
            <Link href={`/admin/kb/articles/${row.original.article_slug}`} className="hover:text-primary max-w-52 truncate font-semibold">
              {row.original.article_title}
            </Link>
          ) : (
            '—'
          ),
      }),
      columnHelper.accessor('message', {
        header: 'Message',
        cell: ({ row }) => (
          <div className="max-w-72">
            <p className="truncate" title={row.original.message ?? ''}>
              {row.original.message ?? '—'}
            </p>
            {row.original.admin_notes && <p className="text-default-400 truncate text-xs">Note: {row.original.admin_notes}</p>}
          </div>
        ),
      }),
      columnHelper.accessor('person', { header: 'From' }),
      columnHelper.accessor('created_at', {
        header: 'Submitted',
        cell: ({ row }) => <span className="text-default-400 text-xs">{row.original.created_at}</span>,
      }),
      columnHelper.accessor('is_resolved', {
        header: 'Status',
        cell: ({ row }) =>
          row.original.is_vote ? (
            <span className="text-default-400 text-xs">—</span>
          ) : (
            <span
              className={cn('badge badge-label', row.original.is_resolved ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning')}
              title={row.original.resolved_by ? `By ${row.original.resolved_by} on ${row.original.resolved_at}` : ''}
            >
              {row.original.is_resolved ? 'Resolved' : 'Open'}
            </span>
          ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Entry> }) => {
          const e = row.original
          return (
            <div className="flex justify-center gap-1.5">
              {!e.is_vote && !e.is_resolved && (
                <button
                  className="btn btn-icon btn-sm bg-success hover:bg-success-hover size-8 rounded-full text-white"
                  onClick={() => resolve(e, true)}
                  title="Mark resolved"
                >
                  <Icon icon="check" className="text-base" />
                </button>
              )}
              {!e.is_vote && e.is_resolved && (
                <button
                  className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
                  onClick={() => resolve(e, false)}
                  title="Reopen"
                >
                  <Icon icon="rotate" className="text-base" />
                </button>
              )}
              <button
                className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
                onClick={() => destroy(e)}
                title="Delete"
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
    data,
    columns,
    state: { sorting, globalFilter, pagination },
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    globalFilterFn: 'includesString',
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
      <Head title="KB Feedback" />
      <PageBreadcrumb title="Feedback" subtitle="Knowledge Base" />

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
                placeholder="Search feedback..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="Nothing here — readers' votes and comments will land in this queue." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="entries"
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
