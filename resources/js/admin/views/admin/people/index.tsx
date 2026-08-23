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
  SortingState,
  useReactTable,
} from '@tanstack/react-table'
import { useMemo, useState } from 'react'

type ContractorRow = {
  id: number
  name: string
  email: string
  phone: string | null
  status: string
  status_label: string
  avatar: string | null
  recruiter: string | null
  properties: string[]
  positions: string[]
}

type StaffRow = {
  id: number
  name: string
  email: string
  phone: string | null
  status: string
  status_label: string
  avatar: string | null
  hire_date: string | null
  roles: string[]
}

type Props = {
  contractors: ContractorRow[] | null
  staff: StaffRow[] | null
  canInvite: boolean
}

const statusBadge: Record<string, string> = {
  contractor_active: 'bg-success/15 text-success',
  staff_active: 'bg-success/15 text-success',
  contractor_inactive: 'bg-secondary/15 text-secondary',
  staff_inactive: 'bg-secondary/15 text-secondary',
  pending_termination: 'bg-warning/15 text-warning',
  terminated: 'bg-danger/15 text-danger',
}

const initialsOf = (name: string) =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

/** Name cell: avatar (photo or initials) + name + email, linking to the profile. */
const PersonCell = ({ id, name, email, avatar }: { id: number; name: string; email: string; avatar: string | null }) => (
  <Link href={`/admin/people/${id}`} className="flex items-center gap-3">
    {avatar ? (
      <img src={avatar} alt={name} className="size-9 shrink-0 rounded-full object-cover" />
    ) : (
      <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
        {initialsOf(name)}
      </span>
    )}
    <span>
      <span className="text-dark hover:text-primary block font-semibold">{name}</span>
      <span className="text-default-400 block text-xs">{email}</span>
    </span>
  </Link>
)

const StatusBadge = ({ status, label }: { status: string; label: string }) => (
  <span className={cn('badge badge-label', statusBadge[status] ?? 'bg-light text-default-600')}>{label}</span>
)

const ViewButton = ({ id }: { id: number }) => (
  <div className="flex justify-center">
    <Link
      href={`/admin/people/${id}`}
      className="btn btn-icon border-default-300 hover:border-default-400 border"
      title="View profile"
    >
      <Icon icon="eye" className="text-base" />
    </Link>
  </div>
)

const contractorColumnHelper = createColumnHelper<ContractorRow>()
const staffColumnHelper = createColumnHelper<StaffRow>()

/** Shared table chrome (search, page size, pagination) around a tanstack table. */
const PeopleTable = <T extends { id: number }>({
  rows,
  columns,
  itemsName,
  emptyMessage,
  action,
}: {
  rows: T[]
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  columns: any[]
  itemsName: string
  emptyMessage: string
  action?: React.ReactNode
}) => {
  const [globalFilter, setGlobalFilter] = useState('')
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 })

  const table = useReactTable({
    data: rows,
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
      <div className="card-header">
        <div className="flex flex-wrap gap-3">
          <div className="input-icon-group">
            <Icon icon="search" className="input-icon" />
            <input
              className="form-input"
              placeholder={`Search ${itemsName}...`}
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
          {action}
        </div>
      </div>

      <DataTable table={table} emptyMessage={emptyMessage} />

      {table.getRowModel().rows.length > 0 && (
        <div className="card-footer">
          <TablePagination
            totalItems={totalItems}
            start={start}
            end={end}
            itemsName={itemsName}
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
    </>
  )
}

const Page = ({ contractors, staff, canInvite }: Props) => {
  const inviteButton = canInvite ? (
    <Link href="/admin/people/invite" className="btn bg-primary hover:bg-primary-hover text-nowrap text-white">
      <Icon icon="user-plus" className="me-1 size-4" /> Invite user
    </Link>
  ) : undefined

  const tabs = [
    { key: 'contractors', label: 'Contractors', show: contractors !== null, count: contractors?.length ?? 0 },
    { key: 'staff', label: 'Staff', show: staff !== null, count: staff?.length ?? 0 },
  ].filter((t) => t.show)

  const [activeTab, setActiveTab] = useState(tabs[0]?.key ?? 'contractors')

  const contractorColumns = useMemo(
    () => [
      contractorColumnHelper.accessor('name', {
        header: 'Contractor',
        cell: ({ row }) => <PersonCell {...row.original} />,
      }),
      contractorColumnHelper.accessor('phone', {
        header: 'Phone',
        cell: ({ row }) => row.original.phone ?? '—',
      }),
      contractorColumnHelper.accessor((row) => row.properties.join(', '), {
        id: 'properties',
        header: 'Properties',
        cell: ({ row }) =>
          row.original.properties.length ? (
            <span>
              {row.original.properties.join(', ')}
              {row.original.positions.length > 0 && (
                <span className="text-default-400 block text-xs">{row.original.positions.join(', ')}</span>
              )}
            </span>
          ) : (
            <span className="text-default-400">No active work orders</span>
          ),
      }),
      contractorColumnHelper.accessor('recruiter', {
        header: 'Recruiter',
        cell: ({ row }) => row.original.recruiter ?? '—',
      }),
      contractorColumnHelper.accessor('status', {
        header: 'Status',
        cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status_label} />,
      }),
      {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }: { row: { original: ContractorRow } }) => <ViewButton id={row.original.id} />,
      },
    ],
    [],
  )

  const staffColumns = useMemo(
    () => [
      staffColumnHelper.accessor('name', {
        header: 'Staff Member',
        cell: ({ row }) => <PersonCell {...row.original} />,
      }),
      staffColumnHelper.accessor('phone', {
        header: 'Phone',
        cell: ({ row }) => row.original.phone ?? '—',
      }),
      staffColumnHelper.accessor((row) => row.roles.join(', '), {
        id: 'roles',
        header: 'Roles',
        cell: ({ row }) => (
          <span className="flex flex-wrap gap-1">
            {row.original.roles.map((role) => (
              <span key={role} className="badge badge-label bg-primary/15 text-primary">
                {role}
              </span>
            ))}
          </span>
        ),
      }),
      staffColumnHelper.accessor('hire_date', {
        header: 'Hired',
        cell: ({ row }) => row.original.hire_date ?? '—',
      }),
      staffColumnHelper.accessor('status', {
        header: 'Status',
        cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status_label} />,
      }),
      {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }: { row: { original: StaffRow } }) => <ViewButton id={row.original.id} />,
      },
    ],
    [],
  )

  return (
    <>
      <Head title="People" />
      <PageBreadcrumb title="People" subtitle="Operations" />

      <div className="card">
        <nav className="border-default-300 flex flex-wrap border-b px-4 pt-2" aria-label="Tabs" role="tablist">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              role="tab"
              aria-selected={activeTab === tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={cn(
                'hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden',
                activeTab === tab.key ? 'border-primary text-primary border-b' : '',
              )}
            >
              {tab.label}
              <span className="text-default-400 ms-1.5 text-xs">({tab.count})</span>
            </button>
          ))}
        </nav>

        {activeTab === 'contractors' && contractors !== null && (
          <PeopleTable rows={contractors} columns={contractorColumns} itemsName="contractors" emptyMessage="No contractors yet." action={inviteButton} />
        )}
        {activeTab === 'staff' && staff !== null && (
          <PeopleTable rows={staff} columns={staffColumns} itemsName="staff" emptyMessage="No staff yet." action={inviteButton} />
        )}
      </div>
    </>
  )
}

export default Page
