import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
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

type Category = {
  id: number
  name: string
  slug: string
  description: string | null
  sort_order: number
  parent_id: number | null
  parent_name: string | null
  is_active: boolean
  articles_count: number
}

type Props = {
  categories: Category[]
}

const emptyForm = {
  name: '',
  description: '',
  parent_id: '',
  sort_order: '0',
  is_active: true,
}

const columnHelper = createColumnHelper<Category>()

const Page = ({ categories }: Props) => {
  const [editing, setEditing] = useState<Category | null>(null)
  const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm(emptyForm)

  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const startEdit = (c: Category) => {
    setEditing(c)
    clearErrors()
    setData({
      name: c.name,
      description: c.description ?? '',
      parent_id: c.parent_id ? String(c.parent_id) : '',
      sort_order: String(c.sort_order),
      is_active: c.is_active,
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
      put(`/admin/kb/categories/${editing.slug}`, { preserveScroll: true, onSuccess: cancelEdit })
    } else {
      post('/admin/kb/categories', { preserveScroll: true, onSuccess: () => reset() })
    }
  }

  const destroy = (c: Category) => {
    if (confirm(`Delete "${c.name}"? Child categories move to the top level.`)) {
      router.delete(`/admin/kb/categories/${c.slug}`, { preserveScroll: true })
    }
  }

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Name',
        cell: ({ row }) => (
          <div>
            <span className="font-semibold">{row.original.name}</span>
            {row.original.description && <p className="text-default-400 max-w-60 truncate text-xs">{row.original.description}</p>}
          </div>
        ),
      }),
      columnHelper.accessor('parent_name', {
        header: 'Parent',
        cell: ({ row }) => row.original.parent_name ?? '—',
      }),
      columnHelper.accessor('sort_order', { header: 'Order' }),
      columnHelper.accessor('articles_count', {
        header: 'Articles',
        cell: ({ row }) => (
          <span className={row.original.articles_count > 0 ? 'text-primary font-semibold' : ''}>{row.original.articles_count}</span>
        ),
      }),
      columnHelper.accessor('is_active', {
        header: 'Status',
        cell: ({ row }) => (
          <span className={cn('badge badge-label', row.original.is_active ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')}>
            {row.original.is_active ? 'Active' : 'Inactive'}
          </span>
        ),
      }),
      {
        header: 'Actions',
        cell: ({ row }: { row: TableRow<Category> }) => (
          <div className="flex justify-center gap-1.5">
            <button
              className="btn btn-icon border-default-300 hover:border-default-400 border"
              onClick={() => startEdit(row.original)}
              title="Edit category"
            >
              <Icon icon="edit" className="text-base" />
            </button>
            {row.original.articles_count === 0 && (
              <button
                className="btn btn-icon border-default-300 hover:border-default-400 border"
                onClick={() => destroy(row.original)}
                title="Delete category"
              >
                <Icon icon="trash" className="text-base" />
              </button>
            )}
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  )

  const table = useReactTable({
    data: categories,
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
      <Head title="KB Categories" />
      <PageBreadcrumb title="Categories" subtitle="Knowledge Base" />

      <div className="gap-base grid lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="card">
            <div className="card-header">
              <div className="flex flex-wrap gap-3">
                <div className="input-icon-group">
                  <Icon icon="search" className="input-icon" />
                  <input
                    className="form-input"
                    placeholder="Search categories..."
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

            <DataTable table={table} emptyMessage="No categories yet — create the first one to organize articles." />

            {table.getRowModel().rows.length > 0 && (
              <div className="card-footer">
                <TablePagination
                  totalItems={totalItems}
                  start={start}
                  end={end}
                  itemsName="categories"
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
            Categories can nest (one level shown per row via Parent). Deleting is blocked while articles are attached.
          </p>
        </div>

        <div>
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">{editing ? `Edit "${editing.name}"` : 'New category'}</h4>
            </div>
            <div className="card-body">
              <form onSubmit={submit} className="space-y-3">
                <div>
                  <label className="form-label">Name</label>
                  <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                  {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
                </div>
                <div>
                  <label className="form-label">Parent (optional)</label>
                  <select className="form-select w-full" value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                    <option value="">Top level</option>
                    {categories
                      .filter((c) => c.id !== editing?.id)
                      .map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                  </select>
                  {errors.parent_id && <p className="text-danger text-sm">{errors.parent_id}</p>}
                </div>
                <div>
                  <label className="form-label">Description</label>
                  <textarea
                    className="form-input w-full"
                    rows={2}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                  />
                </div>
                <div>
                  <label className="form-label">Sort order</label>
                  <input
                    type="number"
                    min={0}
                    className="form-input w-full"
                    value={data.sort_order}
                    onChange={(e) => setData('sort_order', e.target.value)}
                  />
                </div>
                <div className="flex items-center gap-2">
                  <input
                    id="cat-active"
                    type="checkbox"
                    className="form-checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                  />
                  <label htmlFor="cat-active">Active (shown to readers)</label>
                </div>
                <div className="flex gap-2">
                  <button className="btn bg-primary hover:bg-primary-hover flex-1 py-2 font-semibold text-white" disabled={processing}>
                    {editing ? 'Save changes' : 'Create category'}
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
