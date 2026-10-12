
import { cn } from '@/utils/helpers'
import { flexRender, RowData, Table as TableType } from '@tanstack/react-table'
import clsx from 'clsx'
import Icon from '../wrappers/Icon'

declare module '@tanstack/react-table' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData extends RowData, TValue> {
    /** Right-align the header and cells, for numbers that should line up. */
    align?: 'end'
  }
}

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

  /**
   * Open the row's record when the row is clicked. Clicks on a link, button or
   * field inside the row keep their own behaviour; keyboard users reach the
   * record through the row's own link.
   */
  onRowClick?: (row: TData) => void
}

const DataTable = <TData,>({ table, className = '', emptyMessage = 'Nothing found.', showHeaders = true, onRowClick }: DataTableProps<TData>) => {
  'use no memo'
  const columns = table.getAllColumns()
  return (
    <div className={clsx('table-wrapper', className)}>
      <table className="table table-hover">
        {showHeaders && (
          <thead className="thead-sm">
            {table.getHeaderGroups().map((headerGroup) => (
              <tr key={headerGroup.id} className="bg-light/25 text-xs uppercase">
                {headerGroup.headers.map((header) => (
                  <th key={header.id} onClick={header.column.getToggleSortingHandler()} className={cn('select-none', header.column.getCanSort() ? 'cursor-pointer' : 'cursor-default')}>
                    <div
                      className={cn('flex items-center', {
                        'justify-center': header.column.columnDef.header === 'Actions',
                        'justify-end': header.column.columnDef.meta?.align === 'end',
                      })}>
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
              <tr
                key={row.id}
                className={cn(onRowClick && 'cursor-pointer')}
                onClick={
                  onRowClick
                    ? (e) => {
                        if ((e.target as HTMLElement).closest('a, button, input, select, textarea, label')) return
                        onRowClick(row.original)
                      }
                    : undefined
                }>
                {row.getVisibleCells().map((cell) => (
                  <td key={cell.id} className={cn(cell.column.columnDef.meta?.align === 'end' && 'text-end')}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </td>
                ))}
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={columns.length} className="text-center text-default-500 py-3">
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
