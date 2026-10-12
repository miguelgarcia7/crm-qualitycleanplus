import DashCard, { DashEmpty, DashRow } from '@/components/dashboard/DashCard'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'

export type DirectHire = {
  rows: { id: number; name: string | null; property: string | null; worked: number; threshold: number; weeks: number; href: string }[]
  total: number
  withinWeeks: number
}

/** Contractors who reach their property's direct-hire hours soon, at their recent pace. */
const DirectHireCard = ({ data, description }: { data: DirectHire; description: string }) => (
  <DashCard
    title="Close to direct hire"
    description={description}
    footer={data.total > data.rows.length ? <span className="text-default-400">{data.total - data.rows.length} more within {data.withinWeeks} weeks</span> : undefined}>
    {data.rows.length === 0 ? (
      <DashEmpty>Nobody is within {data.withinWeeks} weeks of direct hire.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          end={
            <span className={cn('badge badge-label whitespace-nowrap', row.weeks <= 4 ? 'bg-warning/15 text-warning' : 'bg-light text-default-600')}>
              ~{row.weeks} {row.weeks === 1 ? 'week' : 'weeks'}
            </span>
          }>
          <Link href={row.href} className="text-default-900 hover:text-primary font-medium">
            {row.name}
          </Link>
          <div className="text-default-400 text-sm">
            {row.property} · {row.worked.toLocaleString()} of {row.threshold.toLocaleString()} h
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

export default DirectHireCard
