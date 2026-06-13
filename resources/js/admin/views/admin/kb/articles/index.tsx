import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'
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

type ArticleRow = {
  id: number
  slug: string
  title: string
  status: string
  status_label: string
  version: number
  is_featured: boolean
  view_count: number
  author: string
  categories: string[]
  tags: string[]
  feedback_count: number
  published_at: string | null
  updated_at: string | null
}

type Props = {
  articles: ArticleRow[]
}

const TABS: { key: string; label: string }[] = [
  { key: 'published', label: 'Published' },
  { key: 'draft', label: 'Drafts' },
  { key: 'archived', label: 'Archived' },
  { key: 'all', label: 'All' },
]

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  published: 'bg-success/15 text-success',
  archived: 'bg-secondary/15 text-secondary',
}

const matchesTab = (status: string, tab: string) => tab === 'all' || status === tab

const columnHelper = createColumnHelper<ArticleRow>()

const Page = ({ articles }: Props) => {
  const [activeTab, setActiveTab] = useState('published')
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const counts = useMemo(() => {
    const result: Record<string, number> = {}
    for (const tab of TABS) {
      result[tab.key] = articles.filter((a) => matchesTab(a.status, tab.key)).length
    }
    return result
  }, [articles])

  const data = useMemo(() => articles.filter((a) => matchesTab(a.status, activeTab)), [articles, activeTab])

  const columns = useMemo(
    () => [
      columnHelper.accessor('title', {
        header: 'Title',
        cell: ({ row }) => (
          <div className="max-w-80">
            <Link href={`/admin/kb/articles/${row.original.slug}`} className="hover:text-primary font-semibold">
              {row.original.is_featured && <Icon icon="star-filled" className="text-warning me-1 inline size-3.5" />}
              {row.original.title}
            </Link>
            {row.original.tags.length > 0 && <p className="text-default-400 truncate text-xs">{row.original.tags.join(', ')}</p>}
          </div>
        ),
      }),
      columnHelper.accessor((row) => row.categories.join(', '), {
        id: 'categories',
        header: 'Categories',
        cell: ({ row }) => (row.original.categories.length > 0 ? row.original.categories.join(', ') : '—'),
      }),
      columnHelper.accessor('author', { header: 'Author' }),
      columnHelper.accessor('version', {
        header: 'Version',
        cell: ({ row }) => <span className="text-default-500">v{row.original.version}</span>,
      }),
      columnHelper.accessor('view_count', { header: 'Views' }),
      columnHelper.accessor('feedback_count', {
        header: 'Feedback',
        cell: ({ row }) => (
          <span className={row.original.feedback_count > 0 ? 'text-primary font-semibold' : ''}>{row.original.feedback_count}</span>
        ),
      }),
      columnHelper.accessor('updated_at', {
        header: 'Updated',
        cell: ({ row }) => row.original.updated_at ?? '—',
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
        cell: ({ row }: { row: TableRow<ArticleRow> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/kb/articles/${row.original.slug}`}
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              title="Open article"
            >
              <Icon icon="eye" className="text-base" />
            </Link>
            {row.original.status !== 'archived' && (
              <Link
                href={`/admin/kb/articles/${row.original.slug}/edit`}
                className="btn btn-icon border-default-300 hover:border-default-400 border"
                title="Edit article"
              >
                <Icon icon="edit" className="text-base" />
              </Link>
            )}
          </div>
        ),
      },
    ],
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
      <Head title="KB Articles" />
      <PageBreadcrumb title="Articles" subtitle="Knowledge Base" />

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
                placeholder="Search articles..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <select className="form-select w-20" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
            <Link href="/admin/kb/articles/create" className="btn bg-primary hover:bg-primary-hover text-nowrap text-white">
              <Icon icon="plus" className="me-1 size-4" /> New article
            </Link>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No articles in this view yet." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="articles"
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
