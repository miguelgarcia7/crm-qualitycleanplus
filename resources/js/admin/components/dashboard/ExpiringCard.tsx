import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'

export type Expiring = {
  rows: { id: number; title: string; detail: string; days: number; tone: 'danger' | 'warning' | 'gray'; href: string }[]
  total: number
}

const toneBadge: Record<Expiring['rows'][number]['tone'], string> = {
  danger: 'bg-danger/15 text-danger',
  warning: 'bg-warning/15 text-warning',
  gray: 'bg-light text-default-600',
}

/** Contracts (and, once they exist, certifications) ending in the next 30 days. */
const ExpiringCard = ({ data, description }: { data: Expiring; description: string }) => (
  <DashCard
    title="Expiring in 30 days"
    description={description}
    footer={data.total > data.rows.length ? <ViewAll href="/admin/properties">View all {data.total}</ViewAll> : undefined}>
    {data.rows.length === 0 ? (
      <DashEmpty>Nothing expires in the next 30 days.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          end={
            <span className={cn('badge badge-label whitespace-nowrap', toneBadge[row.tone])}>
              {row.days === 0 ? 'Today' : `${row.days} ${row.days === 1 ? 'day' : 'days'}`}
            </span>
          }>
          <Link href={row.href} className="text-default-900 hover:text-primary font-medium">
            {row.title}
          </Link>
          <div className="text-default-400 text-sm">{row.detail}</div>
        </DashRow>
      ))
    )}
  </DashCard>
)

export default ExpiringCard
