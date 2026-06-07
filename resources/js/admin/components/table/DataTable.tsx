
import { cn } from '@/utils/helpers'
import { flexRender, Table as TableType } from '@tanstack/react-table'
import clsx from 'clsx'
import Icon from '../wrappers/Icon'

type DataTableProps<TData> = {
  /**
   * The table instance from useReactTable
   */
  table: TableType<TData>
  /**
   * Optional class name for the table container
   */
  className?: string
  /**
   * Optional message to display when no data is available
   * @default 'Nothing found.'
   */
  emptyMessage?: React.ReactNode

  /**
   * Optional boolean to display headers
   * @default true
   */
  showHeaders?: boolean
}

const DataTable = <TData,>({ table, className = '', emptyMessage = 'Nothing found.', showHeaders = true }: DataTableProps<TData>) => {
  'use no memo'
  const columns = table.getAllColumns()
  return (
    <div className={clsx('table-wrapper', className)}>
      <table className="table table-hover">
        {showHeaders && (
          <thead className="thead-sm">
            {table.getHeaderGroups().map((headerGroup) => (
              <tr key={headerGroup.id} className="bg-light/25 text-2xs uppercase">
                {headerGroup.headers.map((header) => (
                  <th key={header.id} onClick={header.column.getToggleSortingHandler()} className={cn('select-none', header.column.getCanSort() ? 'cursor-pointer' : 'cursor-default')}>
                    <div className={cn('flex items-center', { 'justify-center': header.column.columnDef.header === 'Actions' })}>
                      {flexRender(header.column.columnDef.header, header.getContext())}
                      {header.column.getCanSort() &&
                        ({
                          asc: <Icon icon="arrow-up" className="ms-1" />,
                          desc: <Icon icon="arrow-down" className="ms-1" />,
                        }[header.column.getIsSorted() as string] ??
                          null)}
                    </div>
                  </th>
                ))}
              </tr>
            ))}
          </thead>
        )}
        <tbody>
          {table.getRowModel().rows?.length ? (
            table.getRowModel().rows.map((row) => (
              <tr key={row.id}>
                {row.getVisibleCells().map((cell) => (
                  <td  key={cell.id}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </td>
                ))}
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={columns.length} className="text-center text-default-400 py-3">
                {emptyMessage}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

export default DataTable
