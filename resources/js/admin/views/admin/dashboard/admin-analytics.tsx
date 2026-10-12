import DashCard, { DashEmpty, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader, { DashboardTab } from '@/components/dashboard/DashboardHeader'
import StatusFilter from '@/components/table/StatusFilter'
import { cn } from '@/utils/helpers'
import { Link, router } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import type { AdminHeader } from './admin-today'

type Headline = { key: string; label: string; value: number | null; note: string; trend: 'up' | 'down' | null }
type Month = { label: string; revenue: number; payouts: number; partial: boolean }
type Bucket = { key: string; label: string; amount: number }
type LateProperty = { id: number; name: string | null; count: number; amount: number }
type PropertyRow = { id: number; name: string; hours: number; billed: number; margin: number | null; overtime: number }
type Week = { label: string; regular: number; overtime: number; share: number; partial: boolean }

type Analytics = {
  period: string
  periods: { key: string; label: string }[]
  periodLabel: string
  headline: Headline[]
  months: Month[]
  owed: { total: number; buckets: Bucket[]; late: LateProperty[]; lateCount: number }
  properties: PropertyRow[]
  weeks: { rows: Week[]; latestShare: number | null; latestLabel: string | null; firstShare: number | null; firstLabel: string | null }
}

/** Overtime past this share of hours is worth a look. */
const OVERTIME_WATCH = 10

const dollars = (n: number, cents = false) =>
  `$${n.toLocaleString('en-US', { minimumFractionDigits: cents ? 2 : 0, maximumFractionDigits: cents ? 2 : 0 })}`

/** "$186k" for chart labels. */
const short = (n: number) => (n >= 1000 ? `$${Math.round(n / 1000).toLocaleString()}k` : dollars(n))

const pct = (n: number | null) => (n === null ? '—' : `${n.toFixed(1)}%`)

const bucketTone: Record<string, { bar: string; text: string }> = {
  current: { bar: 'bg-primary', text: 'text-default-900' },
  late30: { bar: 'bg-warning', text: 'text-warning' },
  late60: { bar: 'bg-danger/60', text: 'text-danger' },
  late90: { bar: 'bg-danger', text: 'text-danger' },
}

const StatCard = ({ stat }: { stat: Headline }) => (
  <div className="card mb-0 h-full">
    <div className="card-body">
      <span className="text-default-500 block text-sm font-medium">{stat.label}</span>
      <span className="text-default-900 mt-1 block text-2xl font-semibold tabular-nums">
        {stat.key === 'margin' ? pct(stat.value) : dollars(stat.value ?? 0)}
      </span>
      <span
        className={cn(
          'mt-0.5 block text-sm',
          stat.trend === 'up' ? 'text-success' : stat.trend === 'down' ? 'text-danger' : 'text-default-400',
        )}>
        {stat.trend === 'up' && '▲ '}
        {stat.trend === 'down' && stat.key !== 'owed' && '▼ '}
        {stat.note}
      </span>
    </div>
  </div>
)

const RevenueChart = ({ months }: { months: Month[] }) => {
  const peak = Math.max(1, ...months.map((m) => Math.max(m.revenue, m.payouts)))
  const summary = months.map((m) => `${m.label}${m.partial ? ' so far' : ''}: revenue ${short(m.revenue)}, payouts ${short(m.payouts)}`).join('; ')

  return (
    <DashCard
      className="xl:col-span-2"
      title="Revenue and payouts"
      description="By month, from frozen invoices. The gap is gross margin."
      aside={
        <span className="text-default-500 flex shrink-0 gap-4 text-sm">
          <span className="inline-flex items-center gap-1.5">
            <span className="bg-primary size-2.5 rounded-sm" />
            Revenue
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="bg-default-300 size-2.5 rounded-sm" />
            Payouts
          </span>
        </span>
      }>
      <div className="pt-4">
        <div role="img" aria-label={`Revenue and payouts by month. ${summary}.`} className="border-default-200 grid h-52 grid-cols-6 items-end gap-3 border-b px-1">
          {months.map((m) => (
            <div key={m.label} className="flex h-full items-end justify-center gap-1" title={`${m.label}: revenue ${dollars(m.revenue)}, payouts ${dollars(m.payouts)}`}>
              <div
                className={cn('w-full max-w-7 rounded-t', m.partial ? 'bg-primary/20 border-primary border-2 border-b-0 border-dashed' : 'bg-primary')}
                style={{ height: `${(m.revenue / peak) * 100}%` }}
              />
              <div
                className={cn('w-full max-w-7 rounded-t', m.partial ? 'bg-default-200 border-default-400 border-2 border-b-0 border-dashed' : 'bg-default-300')}
                style={{ height: `${(m.payouts / peak) * 100}%` }}
              />
            </div>
          ))}
        </div>
        <div className="text-default-500 grid grid-cols-6 gap-3 px-1 pt-2 pb-2 text-center text-sm">
          {months.map((m) => (
            <span key={m.label}>
              <span className="block">{m.partial ? `${m.label} so far` : m.label}</span>
              <span className="text-default-900 block font-medium tabular-nums">{short(m.revenue)}</span>
            </span>
          ))}
        </div>
      </div>
    </DashCard>
  )
}

const WhoOwes = ({ owed }: { owed: Analytics['owed'] }) => {
  const total = owed.buckets.reduce((sum, b) => sum + b.amount, 0)

  return (
    <DashCard
      title="Who owes what"
      description="Unpaid invoices by how late they are."
      footer={<ViewAll href="/admin/invoices?payment=overdue">All overdue invoices</ViewAll>}>
      {total === 0 ? (
        <DashEmpty>Every invoice is paid.</DashEmpty>
      ) : (
        <div className="py-2">
          <div
            role="img"
            aria-label={`Still owed: ${owed.buckets.map((b) => `${b.label} ${dollars(b.amount)}`).join(', ')}`}
            className="bg-light flex h-3.5 overflow-hidden rounded-full">
            {owed.buckets.map((b) => (
              <div key={b.key} className={bucketTone[b.key].bar} style={{ width: `${(b.amount / total) * 100}%` }} />
            ))}
          </div>

          <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            {owed.buckets.map((b) => (
              <div key={b.key}>
                <div className="text-default-500">{b.label}</div>
                <div className={cn('font-semibold tabular-nums', b.amount > 0 ? bucketTone[b.key].text : 'text-default-400')}>{dollars(b.amount)}</div>
              </div>
            ))}
          </div>

          {owed.late.length > 0 && (
            <div className="border-default-200 mt-4 border-t pt-2 text-sm">
              {owed.late.map((p) => (
                <div key={p.id} className="flex justify-between gap-3 py-1.5">
                  <Link href={`/admin/invoices?property_id=${p.id}&payment=overdue`} className="hover:text-primary min-w-0 truncate">
                    {p.name} · {p.count} {p.count === 1 ? 'invoice' : 'invoices'}
                  </Link>
                  <span className="text-default-900 font-semibold tabular-nums">{dollars(p.amount)}</span>
                </div>
              ))}
              {owed.lateCount > owed.late.length && (
                <div className="text-default-400 pt-1">and {owed.lateCount - owed.late.length} more properties behind</div>
              )}
            </div>
          )}
        </div>
      )}
    </DashCard>
  )
}

type Sort = 'billed' | 'margin' | 'overtime'

const SORTS: { key: Sort; label: string }[] = [
  { key: 'billed', label: 'Billed' },
  { key: 'margin', label: 'Lowest margin' },
  { key: 'overtime', label: 'Most overtime' },
]

const PropertiesTable = ({ rows, periodLabel }: { rows: PropertyRow[]; periodLabel: string }) => {
  const [sort, setSort] = useState<Sort>('billed')

  const top = useMemo(() => {
    const sorted = [...rows].sort((a, b) =>
      sort === 'billed' ? b.billed - a.billed : sort === 'margin' ? (a.margin ?? Infinity) - (b.margin ?? Infinity) : b.overtime - a.overtime,
    )
    return sorted.slice(0, 5)
  }, [rows, sort])

  return (
    <DashCard
      className="xl:col-span-2"
      flush
      title={`Properties · ${periodLabel}`}
      description="Top 5. Margin and overtime show where profit leaks."
      aside={rows.length > 1 && <StatusFilter label="Sort properties by" options={SORTS} value={sort} onChange={(key) => setSort(key as Sort)} />}
      footer={rows.length > 5 ? <ViewAll href="/admin/reports/income-vs-payouts">All {rows.length} properties in Reports</ViewAll> : undefined}>
      {rows.length === 0 ? (
        <div className="px-6 py-2">
          <DashEmpty>Nothing was billed in this period.</DashEmpty>
        </div>
      ) : (
        <div className="relative overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th className="ps-6">Property</th>
                <th className="text-end">Hours</th>
                <th className="text-end">Billed</th>
                <th className="text-end">Margin</th>
                <th className="pe-6 text-end">Overtime</th>
              </tr>
            </thead>
            <tbody>
              {top.map((row) => (
                <tr key={row.id}>
                  <td className="ps-6">
                    <Link href={`/admin/properties/${row.id}`} className="text-default-900 hover:text-primary font-medium">
                      {row.name}
                    </Link>
                  </td>
                  <td className="text-end tabular-nums">{row.hours.toLocaleString()}</td>
                  <td className="text-end tabular-nums">{dollars(row.billed)}</td>
                  <td className="text-end tabular-nums">{pct(row.margin)}</td>
                  <td className="pe-6 text-end tabular-nums">
                    {row.overtime >= OVERTIME_WATCH ? <span className="badge badge-label bg-warning/15 text-warning">{pct(row.overtime)}</span> : pct(row.overtime)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </DashCard>
  )
}

const HoursPerWeek = ({ weeks }: { weeks: Analytics['weeks'] }) => {
  const peak = Math.max(1, ...weeks.rows.map((w) => w.regular + w.overtime))

  return (
    <DashCard title="Hours per week" description="Last 8 weeks. The darker part is overtime.">
      <div className="pt-4 pb-2">
        <div
          role="img"
          aria-label={`Hours per week: ${weeks.rows.map((w) => `${w.label} ${w.regular + w.overtime} hours, ${w.overtime} overtime`).join('; ')}`}
          className="border-default-200 grid h-40 grid-cols-8 items-end gap-2 border-b">
          {weeks.rows.map((w) => (
            <div
              key={w.label}
              className={cn('flex h-full flex-col justify-end', w.partial && 'opacity-50')}
              title={`Week of ${w.label}${w.partial ? ' (so far)' : ''}: ${(w.regular + w.overtime).toLocaleString()} h, ${w.overtime.toLocaleString()} h overtime`}>
              <div className="bg-primary rounded-t" style={{ height: `${(w.overtime / peak) * 100}%` }} />
              <div className={cn('bg-primary/40', w.overtime === 0 && 'rounded-t')} style={{ height: `${(w.regular / peak) * 100}%` }} />
            </div>
          ))}
        </div>
        <div className="text-default-400 mt-1.5 flex justify-between text-xs">
          <span>{weeks.rows[0]?.label}</span>
          <span>This week</span>
        </div>
        {weeks.latestShare !== null && (
          <p className="text-default-500 mt-3 text-sm">
            Overtime was <span className="text-default-900 font-semibold">{pct(weeks.latestShare)}</span> of hours in the week of {weeks.latestLabel}
            {weeks.firstShare !== null && `, against ${pct(weeks.firstShare)} in the week of ${weeks.firstLabel}`}.
          </p>
        )}
      </div>
    </DashCard>
  )
}

const Page = ({ analytics, header, tabs, view }: { analytics: Analytics; header: AdminHeader; tabs: DashboardTab[]; view: string }) => {
  const pickPeriod = (period: string) =>
    router.get('/admin/dashboard', { view: 'analytics', period }, { preserveScroll: true, preserveState: true, replace: true })

  return (
    <>
      <DashboardHeader
        greeting={header.greeting}
        subtitle={`All ${header.properties} properties`}
        tabs={tabs}
        current={view}
        aside={<StatusFilter label="Period" options={analytics.periods} value={analytics.period} onChange={pickPeriod} />}
      />

      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
          {analytics.headline.map((stat) => (
            <StatCard key={stat.key} stat={stat} />
          ))}
        </div>

        <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
          <RevenueChart months={analytics.months} />
          <WhoOwes owed={analytics.owed} />
        </div>

        <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
          <PropertiesTable rows={analytics.properties} periodLabel={analytics.periodLabel} />
          <HoursPerWeek weeks={analytics.weeks} />
        </div>
      </div>
    </>
  )
}

export default Page
