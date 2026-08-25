import DataTable from '@/components/table/DataTable'
import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table'
import { useMemo } from 'react'

export type OnTheClockRow = {
  property: string
  count: number
  fill: number
}

export type OnTheClock = {
  title: string
  total: number
  rows: OnTheClockRow[]
  flaggedToday: number
  viewAllHref: string
  emptyText: string
}

const columnHelper = createColumnHelper<OnTheClockRow>()

/** Live ops pulse, gathered by property. */
const OnTheClockCard = ({ data }: { data: OnTheClock }) => {
  const columns = useMemo(
    () => [
      columnHelper.accessor('property', {
        header: 'Property',
        cell: ({ row }) => (
          <>
            <h5>{row.original.property}</h5>
            <span className="text-default-400 text-xs">On shift now</span>
          </>
        ),
      }),
      columnHelper.accessor('count', {
        header: 'Contractors',
        cell: ({ row }) => (
          <>
            <h5>{row.original.count}</h5>
            <span className="text-default-400 text-xs">Contractors</span>
          </>
        ),
      }),
      columnHelper.accessor('fill', {
        header: '',
        enableSorting: false,
        cell: ({ row }) => (
          <span className="bg-light block h-1.5 w-20 overflow-hidden rounded-full">
            <span className="bg-primary block h-full rounded-full" style={{ width: `${Math.max(6, Math.round(row.original.fill * 100))}%` }} />
          </span>
        ),
      }),
    ],
    [],
  )

  const table = useReactTable({
    data: data.rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
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
        <DataTable<OnTheClockRow> table={table} emptyMessage={data.emptyText} className="w-full whitespace-nowrap" showHeaders={false} />
      </div>

      {data.flaggedToday > 0 && (
        <div className="card-footer">
          <p className="text-default-400 flex items-center gap-2 text-sm">
            <Icon icon="map-pin-off" className="text-warning text-base" />
            {data.flaggedToday} {data.flaggedToday === 1 ? 'punch' : 'punches'} flagged today —{' '}
            <Link href={data.viewAllHref} className="text-primary">
              review
            </Link>
          </p>
        </div>
      )}
    </div>
  )
}

export default OnTheClockCard
