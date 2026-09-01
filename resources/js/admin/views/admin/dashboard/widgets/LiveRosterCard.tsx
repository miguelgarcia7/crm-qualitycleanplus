import DataTable from '@/components/table/DataTable'
import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { useMemo } from 'react'

export type LiveRosterRow = {
  id: number
  contractor: string
  property: string
  person_id: number
  started_at: string | null
  minutes: number
  elapsed: string
  /** Past the long-shift threshold — usually a missed clock-out. */
  over: boolean
}

export type LiveRoster = {
  title: string
  total: number
  overCount: number
  thresholdHours: number
  rows: LiveRosterRow[]
  moreCount: number
  viewAllHref: string
  emptyText: string
}

const columnHelper = createColumnHelper<LiveRosterRow>()

/**
 * Who is on the clock right now, person by person, longest shift first.
 *
 * The sibling card counts heads per property; this one names them and says how
 * long each has been on, so a punch nobody closed stands out instead of hiding
 * inside a count.
 */
const LiveRosterCard = ({ data }: { data: LiveRoster }) => {
  const columns = useMemo(
    () => [
      columnHelper.accessor('contractor', {
        header: 'Contractor',
        cell: ({ row }) => (
          <>
            <Link href={`/admin/people/${row.original.person_id}`} className="hover:text-primary">
              <h5>{row.original.contractor}</h5>
            </Link>
            <span className="text-default-400 text-xs">{row.original.property}</span>
          </>
        ),
      }),
      columnHelper.accessor('minutes', {
        header: 'On for',
        cell: ({ row }) => (
          <div className="text-end">
            <h5 className={row.original.over ? 'text-warning' : ''}>
              {row.original.over && <Icon icon="alert-triangle" className="me-1 inline text-sm" />}
              {row.original.elapsed}
            </h5>
            <span className="text-default-400 text-xs">in at {row.original.started_at ?? '—'}</span>
          </div>
        ),
      }),
    ],
    [],
  )

  const table = useReactTable({
    data: data.rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
  })

  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">{data.title}</h4>
        <span className="text-success flex items-center gap-2 text-sm font-semibold">
          <span className="relative flex size-2.5">
            <span className="bg-success absolute inline-flex h-full w-full animate-ping rounded-full opacity-60"></span>
            <span className="bg-success relative inline-flex size-2.5 rounded-full"></span>
          </span>
          {data.total} {data.total === 1 ? 'contractor' : 'contractors'}
        </span>
      </div>

      <div className="card-body p-0">
        <DataTable<LiveRosterRow> table={table} emptyMessage={data.emptyText} className="w-full whitespace-nowrap" showHeaders={false} />
      </div>

      {(data.overCount > 0 || data.moreCount > 0) && (
        <div className="card-footer">
          {data.overCount > 0 && (
            <p className="text-default-400 flex items-center gap-2 text-sm">
              <Icon icon="alert-triangle" className="text-warning text-base" />
              {data.overCount} past {data.thresholdHours}h — check for a missed clock-out
            </p>
          )}
          {data.moreCount > 0 && (
            <p className="text-default-400 mt-1 text-sm">
              +{data.moreCount} more on the clock —{' '}
              <Link href={data.viewAllHref} className="text-primary">
                see all
              </Link>
            </p>
          )}
        </div>
      )}
    </div>
  )
}

export default LiveRosterCard
