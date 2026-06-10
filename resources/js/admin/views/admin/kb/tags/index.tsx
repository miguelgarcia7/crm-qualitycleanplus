import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { Head, router, useForm } from '@inertiajs/react'
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

type Tag = {
  id: number
  name: string
  slug: string
  articles_count: number
}

type Props = {
  tags: Tag[]
}

const columnHelper = createColumnHelper<Tag>()

const Page = ({ tags }: Props) => {
  const [editing, setEditing] = useState<Tag | null>(null)
  const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm({ name: '' })

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 25 })

  const startEdit = (t: Tag) => {
    setEditing(t)
    clearErrors()
    setData({ name: t.name })
  }

  const cancelEdit = () => {
    setEditing(null)
    clearErrors()
    reset()
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      put(`/admin/kb/tags/${editing.slug}`, { preserveScroll: true, onSuccess: cancelEdit })
    } else {
      post('/admin/kb/tags', { preserveScroll: true, onSuccess: () => reset() })
    }
  }

  const destroy = (t: Tag) => {
    if (confirm(`Delete "${t.name}"? It will be removed from ${t.articles_count} article(s).`)) {
      router.delete(`/admin/kb/tags/${t.slug}`, { preserveScroll: true })
    }
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Tag',
        cell: ({ row }) => <span className="badge badge-label bg-primary/15 text-primary">{row.original.name}</span>,
      }),
      columnHelper.accessor('slug', {
        header: 'Slug',
        cell: ({ row }) => <span className="text-default-400">{row.original.slug}</span>,
      }),
      columnHelper.accessor('articles_count', {
        header: 'Articles',
        cell: ({ row }) => (
          <span className={row.original.articles_count > 0 ? 'text-primary font-semibold' : ''}>{row.original.articles_count}</span>
        ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Tag> }) => (
          <div className="flex justify-center gap-1.5">
            <button
              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
              onClick={() => startEdit(row.original)}
              title="Rename tag"
            >
              <Icon icon="edit" className="text-base" />
            </button>
            <button
              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
              onClick={() => destroy(row.original)}
              title="Delete tag"
            >
              <Icon icon="trash" className="text-base" />
            </button>
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  )

  const table = useReactTable({
    data: tags,
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

  return (
    <>
      <Head title="KB Tags" />
      <PageBreadcrumb title="Tags" subtitle="Knowledge Base" />

      <div className="gap-base grid lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="card">
            <div className="card-header">
              <div className="flex flex-wrap gap-3">
                <div className="input-icon-group">
                  <Icon icon="search" className="input-icon" />
                  <input
                    className="form-input"
                    placeholder="Search tags..."
                    value={globalFilter}
                    onChange={(e) => setGlobalFilter(e.target.value)}
                  />
                </div>
              </div>
              <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
                <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
                  {[25, 50, 100].map((size) => (
                    <option key={size}>{size}</option>
                  ))}
                </select>
              </div>
            </div>

            <DataTable table={table} emptyMessage="No tags yet — they are usually created from the article form." />

            {table.getRowModel().rows.length > 0 && (
              <div className="card-footer">
                <TablePagination
                  totalItems={totalItems}
                  start={start}
                  end={end}
                  itemsName="tags"
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
        </div>

        <div>
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">{editing ? `Rename "${editing.name}"` : 'New tag'}</h4>
            </div>
            <div className="card-body">
              <form onSubmit={submit} className="space-y-3">
                <div>
                  <label className="form-label">Name</label>
                  <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                  {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
                </div>
                <div className="flex gap-2">
                  <button className="btn bg-primary hover:bg-primary-hover flex-1 py-2 font-semibold text-white" disabled={processing}>
                    {editing ? 'Save' : 'Create tag'}
                  </button>
                  {editing && (
                    <button type="button" className="btn btn-light" onClick={cancelEdit}>
                      Cancel
                    </button>
                  )}
                </div>
                <p className="text-default-400 text-xs">Renaming keeps the slug (links stay stable). Tags are auto-created from the article form too.</p>
              </form>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
