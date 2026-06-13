import { ReactNode } from 'react'

/**
 * Shared in-card report table: header row, body rows, and a bold totals
 * footer. Columns marked numeric are right-aligned.
 */
type Column = { label: string; numeric?: boolean }

type Props = {
  columns: Column[]
  rows: ReactNode[][]
  totals?: ReactNode[]
  emptyMessage: string
}

const ReportTable = ({ columns, rows, totals, emptyMessage }: Props) => (
  <div className="table-wrapper">
    <table className="table">
      <thead className="thead-sm">
        <tr className="bg-light/25 text-xs uppercase">
          {columns.map((column) => (
            <th key={column.label} className={column.numeric ? 'text-end' : ''}>
              {column.label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.length === 0 ? (
          <tr>
            <td colSpan={columns.length} className="text-default-400 py-8 text-center">
              {emptyMessage}
            </td>
          </tr>
        ) : (
          rows.map((cells, i) => (
            <tr key={i}>
              {cells.map((cell, j) => (
                <td key={j} className={columns[j]?.numeric ? 'text-end' : ''}>
                  {cell}
                </td>
              ))}
            </tr>
          ))
        )}
      </tbody>
      {totals && rows.length > 0 && (
        <tfoot>
          <tr className="bg-light/40 font-semibold">
            {totals.map((cell, j) => (
              <td key={j} className={columns[j]?.numeric ? 'text-end' : ''}>
                {cell}
              </td>
            ))}
          </tr>
        </tfoot>
      )}
    </table>
  </div>
)

export default ReportTable
