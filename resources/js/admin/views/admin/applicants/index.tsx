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

type ApplicationRow = {
  id: number
  name: string
  email: string | null
  phone: string | null
  city: string
  desired_position: string | null
  posting: string | null
  status: string
  status_label: string
  submitted_at: string
  submitted_at_display: string
}

type Props = {
  applications: ApplicationRow[]
  filter: string
}

const TABS: { key: string; label: string }[] = [
  { key: 'pending', label: 'Pending' },
  { key: 'submitted', label: 'Submitted' },
  { key: 'reviewing', label: 'Reviewing' },
  { key: 'promoted', label: 'Promoted' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'all', label: 'All' },
]

const statusBadge: Record<string, string> = {
  submitted: 'bg-info/15 text-info',
  reviewing: 'bg-warning/15 text-warning',
  promoted: 'bg-success/15 text-success',
  rejected: 'bg-danger/15 text-danger',
}

const matchesTab = (status: string, tab: string) => {
  if (tab === 'all') return true
  if (tab === 'pending') return status === 'submitted' || status === 'reviewing'
  return status === tab
}

const columnHelper = createColumnHelper<ApplicationRow>()

const Page = ({ applications, filter }: Props) => {
  const [activeTab, setActiveTab] = useState(TABS.some((t) => t.key === filter) ? filter : 'pending')
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([{ id: 'submitted_at', desc: true }])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const counts = useMemo(() => {
    const result: Record<string, number> = {}
    for (const tab of TABS) {
      result[tab.key] = applications.filter((a) => matchesTab(a.status, tab.key)).length
    }
    return result
  }, [applications])

  const data = useMemo(() => applications.filter((a) => matchesTab(a.status, activeTab)), [applications, activeTab])

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: 'Applicant',
        cell: ({ row }) => (
          <div>
            <Link href={`/admin/applicants/${row.original.id}`} className="hover:text-primary font-semibold">
              {row.original.name}
            </Link>
            {row.original.email && <p className="text-default-400 text-xs">{row.original.email}</p>}
          </div>
        ),
      }),
      columnHelper.accessor('phone', {
        header: 'Phone',
        cell: ({ row }) => row.original.phone ?? '—',
      }),
      columnHelper.accessor('city', {
        header: 'Location',
        cell: ({ row }) => row.original.city || '—',
      }),
      columnHelper.accessor('desired_position', {
        header: 'Position',
        cell: ({ row }) => row.original.desired_position ?? '—',
      }),
      columnHelper.accessor('posting', {
        header: 'Posting',
        cell: ({ row }) => row.original.posting ?? '—',
      }),
      columnHelper.accessor('submitted_at', {
        header: 'Submitted',
        cell: ({ row }) => row.original.submitted_at_display,
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
        cell: ({ row }: { row: TableRow<ApplicationRow> }) => (
          <div className="flex justify-center gap-1.5">
            <Link
              href={`/admin/applicants/${row.original.id}`}
              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
              title="Open application"
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
      <Head title="Applicants" />
      <PageBreadcrumb title="Applicants" subtitle="Recruiting" />

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
                placeholder="Search applicants..."
                value={globalFilter}
                onChange={(e) => setGlobalFilter(e.target.value)}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <span className="text-default-400 text-sm text-nowrap">Rows per page</span>
            <select className="form-select w-auto" value={pageSize} onChange={(e) => table.setPageSize(Number(e.target.value))}>
              {[10, 25, 50, 100].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable table={table} emptyMessage="No applications match this view." />

        {table.getRowModel().rows.length > 0 && (
          <div className="card-footer">
            <TablePagination
              totalItems={totalItems}
              start={start}
              end={end}
              itemsName="applications"
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
