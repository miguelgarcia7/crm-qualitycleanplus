import DataTable from '@/components/table/DataTable'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table'
import { useMemo } from 'react'

export type DecisionRow = {
  icon: string
  tone: 'default' | 'warning' | 'danger'
  label: string
  sublabel?: string | null
  href: string
}

export type Decisions = {
  title: string
  rows: DecisionRow[]
}

const toneClasses: Record<DecisionRow['tone'], string> = {
  default: 'bg-primary/15 text-primary',
  warning: 'bg-warning/15 text-warning',
  danger: 'bg-danger/15 text-danger',
}

const columnHelper = createColumnHelper<DecisionRow>()

/** Clocks running outside the billing chain — contracts, applicants, PTO, visits. */
const DecisionsCard = ({ decisions }: { decisions: Decisions }) => {
  const columns = useMemo(
    () => [
      columnHelper.accessor('icon', {
        header: '',
        enableSorting: false,
        cell: ({ row }) => (
          <span className={cn('flex size-9 items-center justify-center rounded-full', toneClasses[row.original.tone])}>
            <Icon icon={row.original.icon} className="text-base" />
          </span>
        ),
      }),
      columnHelper.accessor('label', {
        header: 'Item',
        cell: ({ row }) => (
          <>
            <h5>{row.original.label}</h5>
            {row.original.sublabel && <span className="text-default-400 text-xs">{row.original.sublabel}</span>}
          </>
        ),
      }),
      columnHelper.accessor('href', {
        header: '',
        enableSorting: false,
        cell: ({ row }) => (
          <Link href={row.original.href} className="btn btn-icon border-default-300 hover:border-default-400 border" title={`Open ${row.original.label}`}>
            <Icon icon="chevron-right" className="text-base" />
          </Link>
        ),
      }),
    ],
    [],
  )

  const table = useReactTable({
    data: decisions.rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
  })

  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">{decisions.title}</h4>
      </div>

      <div className="card-body p-0">
        <DataTable<DecisionRow> table={table} emptyMessage="Nothing needs deciding right now." className="w-full whitespace-nowrap" showHeaders={false} />
      </div>
    </div>
  )
}

export default DecisionsCard
