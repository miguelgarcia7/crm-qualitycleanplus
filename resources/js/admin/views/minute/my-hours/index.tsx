import PageBreadcrumb from '@/components/PageBreadcrumb'
import { money, weekRange } from '@/components/timesheet/PmTimesheet'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { WeekStatusPill, hours1, type HoursWeek } from './shared'

type Props = {
  weeks: HoursWeek[]
  summary: { minutes: number; overtime_minutes: number; gross: number; weeks: number; ot_rate: number | null }
  filters: { range: '3m' | 'year' | 'all'; job: number | null }
  jobs: { id: number; label: string }[]
}

const RANGES = [
  { key: '3m', label: 'Last 3 months', title: 'Last 3 months' },
  { key: 'year', label: 'This year', title: 'This year' },
  { key: 'all', label: 'All', title: 'Since you started' },
] as const

/** "October", or "October 2025" outside the current year. */
const monthName = (date: string) => {
  const d = new Date(date + 'T12:00:00')
  return d.toLocaleDateString('en-US', { month: 'long', ...(d.getFullYear() !== new Date().getFullYear() && { year: 'numeric' }) })
}

const Page = ({ weeks, summary, filters, jobs }: Props) => {
  const go = (next: Partial<Props['filters']>) =>
    router.get('/my-hours', { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true })

  // Weeks are grouped by the month they start in.
  const months = weeks.reduce<{ name: string; weeks: HoursWeek[] }[]>((list, w) => {
    const name = monthName(w.start)
    const last = list[list.length - 1]
    if (last?.name === name) last.weeks.push(w)
    else list.push({ name, weeks: [w] })
    return list
  }, [])

  return (
    <>
      <Head title="My hours" />
      <PageBreadcrumb title="My hours" />
      <p className="text-default-500 -mt-2 mb-4">Every week you've worked, and your pay before taxes and deductions.</p>

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div role="group" aria-label="Show weeks from" className="bg-default-100 inline-flex flex-1 rounded-lg p-1 sm:flex-none">
          {RANGES.map((r) => (
            <button
              key={r.key}
              type="button"
              aria-pressed={filters.range === r.key}
              onClick={() => go({ range: r.key })}
              className={cn(
                'min-h-10 flex-1 rounded-md px-3.5 text-sm font-semibold whitespace-nowrap sm:flex-none',
                filters.range === r.key ? 'bg-card text-default-900 shadow-sm' : 'text-default-500 hover:text-default-900',
              )}>
              {r.label}
            </button>
          ))}
        </div>
        {jobs.length > 1 && (
          <select
            aria-label="Job"
            className="form-input w-full sm:w-auto"
            value={filters.job ?? ''}
            onChange={(e) => go({ job: e.target.value ? Number(e.target.value) : null })}>
            <option value="">All jobs</option>
            {jobs.map((j) => (
              <option key={j.id} value={j.id}>
                {j.label}
              </option>
            ))}
          </select>
        )}
      </div>

      <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
        <section className="card mb-0 lg:sticky lg:top-24" aria-labelledby="summary-title">
          <div className="card-body p-5">
            <h2 id="summary-title" className="text-default-500 text-xs font-semibold tracking-wide uppercase">
              {RANGES.find((r) => r.key === filters.range)?.title}
            </h2>
            <div className="mt-3 grid grid-cols-3 gap-3">
              <div>
                <div className="text-default-900 text-xl font-semibold tabular-nums">{hours1(summary.minutes)}</div>
                <div className="text-default-500 text-sm">hours</div>
              </div>
              <div>
                <div className="text-default-900 text-xl font-semibold tabular-nums">${Math.round(summary.gross / 100).toLocaleString('en-US')}</div>
                <div className="text-default-500 text-sm">gross pay</div>
              </div>
              <div>
                <div className="text-default-900 text-xl font-semibold tabular-nums">{summary.weeks}</div>
                <div className="text-default-500 text-sm">{summary.weeks === 1 ? 'week' : 'weeks'}</div>
              </div>
            </div>
            <p className="text-default-500 border-default-200 mt-4 border-t pt-3 text-sm">
              {summary.overtime_minutes > 0
                ? `Includes ${hours1(summary.overtime_minutes)} h of overtime${summary.ot_rate ? ` at ${money(summary.ot_rate)}` : ''}. `
                : ''}
              Overtime counts after 40 h in a week, for each job on its own.
            </p>
          </div>
        </section>

        <div className="flex flex-col gap-4 lg:col-span-2">
          {months.length ? (
            months.map((m) => {
              const minutes = m.weeks.reduce((t, w) => t + w.minutes, 0)
              const gross = m.weeks.reduce((t, w) => t + w.gross, 0)
              return (
                <section key={m.name} className="card mb-0 overflow-hidden" aria-label={m.name}>
                  <div className="flex items-baseline justify-between gap-3 px-5 pt-4 pb-2">
                    <h2 className="text-default-900 text-lg font-semibold">{m.name}</h2>
                    <span className="text-default-500 text-sm tabular-nums">
                      {hours1(minutes)} h · {money(gross)}
                    </span>
                  </div>
                  {m.weeks.map((w) => (
                    <Link
                      key={w.start}
                      href={`/my-hours/${w.start}`}
                      className="border-default-200 hover:bg-default-50 flex min-h-11 items-center justify-between gap-3 border-t px-5 py-3.5">
                      <span className="flex min-w-0 flex-col gap-1">
                        <span className="text-default-900 font-semibold">{weekRange(w.start, w.end)}</span>
                        <span className="flex flex-wrap items-center gap-2">
                          <WeekStatusPill week={w} />
                          <span className="text-default-500 text-sm tabular-nums">{hours1(w.minutes)} h</span>
                        </span>
                      </span>
                      <span className="flex items-center gap-2">
                        <strong className="text-default-900 text-base tabular-nums">{money(w.gross)}</strong>
                        <Icon icon="chevron-right" className="text-default-500" />
                      </span>
                    </Link>
                  ))}
                </section>
              )
            })
          ) : (
            <section className="card mb-0">
              <div className="card-body text-default-500 p-5">No hours in this period. Try a longer range.</div>
            </section>
          )}

          <p className="text-default-500 text-sm">
            Weeks the hotel hasn't approved can still change. Something wrong with a week? Open it and choose Report a problem.
          </p>
        </div>
      </div>
    </>
  )
}

export default Page
