import DataTable from '@/components/table/DataTable'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, getSortedRowModel, SortingState, useReactTable } from '@tanstack/react-table'
import { useMemo, useState } from 'react'

export type ApprovalRow = {
  id: number
  property: string | null
  week: string | null
  recruiter: string | null
  hours: number
  billable: number
  waiting: string | null
  overdue: boolean
  href: string
}

export type Approvals = {
  title: string
  total: number
  rows: ApprovalRow[]
  viewAllHref: string
  emptyText: string
}

const columnHelper = createColumnHelper<ApprovalRow>()

const money = (value: number) => `$${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

/** Timesheets sitting with a property manager, oldest first. */
const ApprovalsTable = ({ approvals }: { approvals: Approvals }) => {
  const [sorting, setSorting] = useState<SortingState>([])

  const columns = useMemo(
    () => [
      columnHelper.accessor('property', {
        header: 'Property',
        cell: ({ row }) => (
          <>
            <Link href={row.original.href} className="hover:text-primary font-semibold">
              {row.original.property}
            </Link>
            {row.original.week && <span className="text-default-400 block text-xs">Week of {row.original.week}</span>}
          </>
        ),
      }),
      columnHelper.accessor('recruiter', {
        header: 'Recruiter',
        cell: ({ row }) => row.original.recruiter ?? '—',
      }),
      columnHelper.accessor('hours', {
        header: 'Hours',
        cell: ({ row }) => row.original.hours.toLocaleString(),
      }),
      columnHelper.accessor('billable', {
        header: 'Billable',
        cell: ({ row }) => money(row.original.billable),
      }),
      columnHelper.accessor('waiting', {
        header: 'Waiting',
        cell: ({ row }) => (
          <span className={cn('badge badge-label', row.original.overdue ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning')}>
            {row.original.waiting ?? '—'}
          </span>
        ),
      }),
    ],
    [],
  )

  const table = useReactTable({
    data: approvals.rows,
    columns,
    state: { sorting },
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
  })

  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">
          {approvals.title}
          {approvals.total > 0 && <span className="text-default-400 ms-1 text-sm font-normal">({approvals.total} waiting)</span>}
        </h4>
        <div>
          <Link href={approvals.viewAllHref} className="btn btn-sm border-default-300 hover:border-default-400 border font-semibold">
            <Icon icon="file-invoice" /> Open billing
          </Link>
        </div>
      </div>

      <div className="card-body p-0">
        <DataTable<ApprovalRow> table={table} emptyMessage={approvals.emptyText} className="table-centered table-hover" />
      </div>
    </div>
  )
}

export default ApprovalsTable
