import DataTable from '@/components/table/DataTable'
import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table'
import { useMemo } from 'react'

export type TaskRow = {
  id: number
  label: string
  sublabel?: string | null
  icon: string
  age?: string | null
}

export type Tasks = {
  title: string
  total: number
  rows: TaskRow[]
  viewAllHref: string
  emptyText: string
}

const columnHelper = createColumnHelper<TaskRow>()

/** The signed-in person's open workflow steps, inline from My Tasks. */
const TasksCard = ({ tasks }: { tasks: Tasks }) => {
  const columns = useMemo(
    () => [
      columnHelper.accessor('icon', {
        header: '',
        enableSorting: false,
        cell: ({ row }) => (
          <span className="bg-primary/15 text-primary flex size-9 items-center justify-center rounded-full">
            <Icon icon={row.original.icon} className="text-base" />
          </span>
        ),
      }),
      columnHelper.accessor('label', {
        header: 'Task',
        cell: ({ row }) => (
          <>
            <h5>{row.original.label}</h5>
            {row.original.sublabel && <span className="text-default-400 text-xs">{row.original.sublabel}</span>}
          </>
        ),
      }),
      columnHelper.accessor('age', {
        header: 'Waiting',
        cell: ({ row }) => (
          <>
            <h5>{row.original.age ?? '—'}</h5>
            <span className="text-default-400 text-xs">Waiting</span>
          </>
        ),
      }),
    ],
    [],
  )

  const table = useReactTable({
    data: tasks.rows,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
  })

  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">{tasks.title}</h4>
        {tasks.total > 0 && <span className="badge bg-warning/15 text-warning rounded-full px-2.5 text-xs">{tasks.total} waiting</span>}
      </div>

      <div className="card-body p-0">
        <DataTable<TaskRow> table={table} emptyMessage={tasks.emptyText} className="w-full whitespace-nowrap" showHeaders={false} />
      </div>

      {tasks.total > 0 && (
        <div className="card-footer">
          <Link href={tasks.viewAllHref} className="text-primary text-sm">
            View all {tasks.total} tasks →
          </Link>
        </div>
      )}
    </div>
  )
}

export default TasksCard
