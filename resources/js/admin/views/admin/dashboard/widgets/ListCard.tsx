import { Link } from '@inertiajs/react'

export type ListRow = {
  label: string
  sublabel?: string
  meta?: string
  href?: string
  tone?: 'default' | 'warning' | 'danger' | 'success'
}

export type ListWidget = {
  title: string
  rows: ListRow[]
  viewAllHref?: string
  viewAllLabel?: string
  emptyText?: string
}

const metaTone: Record<NonNullable<ListRow['tone']>, string> = {
  default: 'text-default-500',
  warning: 'text-warning',
  danger: 'text-danger',
  success: 'text-success',
}

const Row = ({ row }: { row: ListRow }) => {
  const inner = (
    <div className="flex items-center justify-between gap-3 py-2.5">
      <div className="min-w-0">
        <div className="truncate text-sm font-medium">{row.label}</div>
        {row.sublabel && <div className="text-default-400 truncate text-xs">{row.sublabel}</div>}
      </div>
      {row.meta && <span className={`shrink-0 text-sm font-medium ${metaTone[row.tone ?? 'default']}`}>{row.meta}</span>}
    </div>
  )

  return row.href ? (
    <Link href={row.href} className="border-default-100 hover:bg-default-50 block border-b px-1 last:border-0">
      {inner}
    </Link>
  ) : (
    <div className="border-default-100 border-b px-1 last:border-0">{inner}</div>
  )
}

const ListCard = ({ widget }: { widget: ListWidget }) => (
  <div className="card h-full rounded-2xl">
    <div className="card-header flex items-center justify-between p-5 pb-2">
      <h4 className="card-title">{widget.title}</h4>
      {widget.viewAllHref && (
        <Link href={widget.viewAllHref} className="text-primary text-sm">
          {widget.viewAllLabel ?? 'View all'} →
        </Link>
      )}
    </div>
    <div className="card-body p-5 pt-0">
      {widget.rows.length === 0 ? (
        <p className="text-default-400 py-4 text-sm">{widget.emptyText ?? 'Nothing here right now.'}</p>
      ) : (
        widget.rows.map((row, i) => <Row key={i} row={row} />)
      )}
    </div>
  </div>
)

export default ListCard
