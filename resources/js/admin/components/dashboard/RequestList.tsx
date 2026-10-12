import DashCard, { DashEmpty, DashRow } from '@/components/dashboard/DashCard'
import { Link } from '@inertiajs/react'

export type RequestRow = { key: string; type: string; title: string; detail: string; age: string | null; action: { label: string; href: string } }

export type Requests = {
  rows: RequestRow[]
  total: number
  /** Requests not on screen. */
  hidden: number
  byType: { label: string; count: number; href: string }[]
}

type Props = { title: string; description: string; data: Requests; emptyText: string; className?: string }

/**
 * Every request a role decides on, one list across types, oldest first, each
 * with its next action. When some don't fit, the footer links to each type's
 * own page.
 */
const RequestList = ({ title, description, data, emptyText, className }: Props) => (
  <DashCard
    className={className}
    title={title}
    description={description}
    aside={data.total > 0 && <span className="badge badge-label bg-light text-default-600 shrink-0">{data.total} waiting</span>}
    footer={
      data.hidden > 0 ? (
        <div className="flex flex-wrap gap-x-5 gap-y-1">
          {data.byType.map((type) => (
            <Link key={type.label} href={type.href} className="text-primary font-medium">
              {type.label} ({type.count}) →
            </Link>
          ))}
        </div>
      ) : undefined
    }>
    {data.rows.length === 0 ? (
      <DashEmpty>{emptyText}</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.key}
          end={
            <>
              {row.age && <span className="text-default-400 hidden text-sm whitespace-nowrap sm:inline">{row.age}</span>}
              <Link href={row.action.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
                {row.action.label}
              </Link>
            </>
          }>
          <div className="flex items-start gap-3">
            <span className="badge badge-label bg-info/15 text-info mt-0.5 hidden w-28 shrink-0 justify-center sm:inline-flex">{row.type}</span>
            <div className="min-w-0">
              <div className="text-default-900 font-medium">{row.title}</div>
              <div className="text-default-400 text-sm">
                <span className="sm:hidden">{row.type} · </span>
                {row.detail}
              </div>
            </div>
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

export default RequestList
