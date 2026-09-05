import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, SortingState, useReactTable } from '@tanstack/react-table'
import { useEffect, useMemo, useRef, useState } from 'react'

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

type Filters = {
  tab: string
  search: string
  status: string
  sort: string
  direction: string
  per_page: number
}

type Props = {
  /** Rows for the tab currently showing — contractors or staff, never both. */
  people: (ContractorRow | StaffRow)[]
  pagination: PaginationMeta
  filters: Filters
  counts: { contractors: number | null; staff: number | null }
  statuses: { value: string; label: string }[]
  can: { contractors: boolean; staff: boolean; invite: boolean }
}

/** Drop empties so the URL carries only what is actually filtering. */
const queryFrom = (filters: Filters, page: number): Record<string, string> => {
  const params: Record<string, string> = {}
  if (filters.tab !== 'contractors') params.tab = filters.tab
  if (filters.search !== '') params.search = filters.search
  if (filters.status !== '') params.status = filters.status
  if (filters.sort !== 'name') params.sort = filters.sort
  if (filters.direction !== 'asc') params.direction = filters.direction
  if (filters.per_page !== 25) params.per_page = String(filters.per_page)
  if (page > 1) params.page = String(page)
  return params
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

const Page = ({ people, pagination, filters, counts, statuses, can }: Props) => {
  const [search, setSearch] = useState(filters.search)
  const onContractors = filters.tab === 'contractors'

  /**
   * Every control routes through here: the query string is the state, so a
   * filtered view is a link someone can send, the tab survives a refresh,
   * and Back steps through filters.
   */
  const visit = (changes: Partial<Filters>, page = 1, replace = false) => {
    router.get('/admin/people', queryFrom({ ...filters, ...changes }, page), {
      preserveState: true,
      preserveScroll: true,
      replace,
      only: ['people', 'pagination', 'filters', 'counts', 'statuses'],
    })
  }

  // Debounced so typing does not fire a request per keystroke.
  const typed = useRef(false)
  useEffect(() => {
    if (!typed.current || search === filters.search) return
    const timer = setTimeout(() => visit({ search }, 1, true), 300)
    return () => clearTimeout(timer)
  }, [search]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => setSearch(filters.search), [filters.search])

  const sorting: SortingState = useMemo(
    () => [{ id: filters.sort, desc: filters.direction === 'desc' }],
    [filters.sort, filters.direction],
  )

  // Switching tabs drops status and sort: both are tab-specific, and a
  // status from the other side of the lifecycle would match nothing.
  const selectTab = (tab: string) => visit({ tab, status: '', sort: 'name', direction: 'asc' })

  const inviteButton = can.invite ? (
    <Link href="/admin/people/invite" className="btn bg-primary hover:bg-primary-hover text-nowrap text-white">
      <Icon icon="user-plus" className="me-1 size-4" /> Invite user
    </Link>
  ) : undefined

  const tabs = [
    { key: 'contractors', label: 'Contractors', show: can.contractors, count: counts.contractors ?? 0 },
    { key: 'staff', label: 'Staff', show: can.staff, count: counts.staff ?? 0 },
  ].filter((t) => t.show)

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
        // A list assembled from work orders; there is no column to order by.
        enableSorting: false,
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
        enableSorting: false,
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
        enableSorting: false,
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
        enableSorting: false,
        cell: ({ row }: { row: { original: StaffRow } }) => <ViewButton id={row.original.id} />,
      },
    ],
    [],
  )

  const table = useReactTable({
    data: people,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    columns: (onContractors ? contractorColumns : staffColumns) as any,
    state: { sorting },
    // The database orders and slices; the table only renders what it is given.
    manualSorting: true,
    manualPagination: true,
    pageCount: pagination.last_page,
    onSortingChange: (updater) => {
      const next = typeof updater === 'function' ? updater(sorting) : updater
      const [first] = next
      visit(first ? { sort: first.id, direction: first.desc ? 'desc' : 'asc' } : { sort: 'name', direction: 'asc' })
    },
    getCoreRowModel: getCoreRowModel(),
  })

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
              aria-selected={filters.tab === tab.key}
              onClick={() => selectTab(tab.key)}
              className={cn(
                'hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden',
                filters.tab === tab.key ? 'border-primary text-primary border-b' : '',
              )}>
              {tab.label}
              <span className="text-default-400 ms-1.5 text-xs">({tab.count})</span>
            </button>
          ))}
        </nav>

        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input"
                placeholder="Search name, email or phone..."
                value={search}
                onChange={(e) => {
                  typed.current = true
                  setSearch(e.target.value)
                }}
              />
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <select className="form-select w-auto min-w-40" value={filters.status} onChange={(e) => visit({ status: e.target.value })}>
              <option value="">Status</option>
              {statuses.map((status) => (
                <option key={status.value} value={status.value}>
                  {status.label}
                </option>
              ))}
            </select>
            <select className="form-select w-20" value={filters.per_page} onChange={(e) => visit({ per_page: Number(e.target.value) })}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
            {inviteButton}
          </div>
        </div>

        <DataTable table={table} emptyMessage={onContractors ? 'No contractors match this view.' : 'No staff match this view.'} />

        {pagination.total > 0 && (
          <div className="card-footer">
            <ServerPagination
              meta={pagination}
              itemsName={onContractors ? 'contractors' : 'staff'}
              onPageChange={(page) => visit({}, page)}
            />
          </div>
        )}
      </div>
    </>
  )
}

export default Page
