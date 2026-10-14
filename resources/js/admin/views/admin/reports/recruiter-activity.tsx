import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Stat } from '@/components/timesheet/WeekSummary'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'

type Row = {
  id: number
  name: string
  visits: number
  hotels_visited: number
  hotels_assigned: number
  not_visited: number
  avg_minutes: number | null
  off_site: number
  late: number
  covering: number
  open_now: number
  by_week: number[]
}

type Props = {
  filters: { period: 'week' | 'month'; at: string; recruiter_id: number | null }
  label: string
  previous: string
  next: string | null
  range: { from: string; to: string }
  summary: {
    visits: number
    previous_visits: number
    hotels_visited: number
    hotels_assigned: number
    avg_minutes: number | null
    off_site: number
    late: number
    open_now: number
  }
  rows: Row[]
  weeks: string[]
  stale: { id: number; hotel: string; recruiters: string; last: string | null; days: number | null }[]
  staleDays: number
  recruiters: { id: number; name: string }[]
}

const fmtMinutes = (m: number | null) => (m === null ? '—' : m >= 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m} min`)

/** A number that opens those visits in Field Visits; a zero stays plain. */
const VisitsLink = ({ n, href, warn }: { n: number; href: string; warn?: boolean }) =>
  n === 0 ? (
    <span className="text-default-500">0</span>
  ) : (
    <Link href={href} className={cn('font-semibold underline-offset-3 hover:underline', warn ? 'text-warning' : 'text-default-900')}>
      {n}
    </Link>
  )

const Page = ({ filters, label, previous, next, range, summary, rows, weeks, stale, staleDays, recruiters }: Props) => {
  const go = (changes: Partial<Props['filters']>) => {
    const f = { ...filters, ...changes }
    const params: Record<string, string> = {}
    if (f.period === 'week') params.period = 'week'
    params.at = f.at
    if (f.recruiter_id) params.recruiter_id = String(f.recruiter_id)
    router.get('/admin/reports/recruiter-activity', params, { preserveState: true, preserveScroll: true })
  }

  const exportQuery = new URLSearchParams({
    ...(filters.period === 'week' ? { period: 'week' } : {}),
    at: filters.at,
    ...(filters.recruiter_id ? { recruiter_id: String(filters.recruiter_id) } : {}),
  }).toString()

  // Field Visits, narrowed to this period (and a recruiter or a flag).
  const visitsHref = (extra: Record<string, string> = {}) =>
    `/admin/field-visits?${new URLSearchParams({ range: 'custom', from: range.from, to: range.to, ...extra }).toString()}`

  const needLook = summary.off_site + summary.late + summary.open_now
  const change = summary.visits - summary.previous_visits

  return (
    <>
      <Head title="Recruiter Activity" />
      <PageBreadcrumb title="Recruiter Activity" subtitle="Reports" />

      <div className="card">
        <div className="card-header flex flex-wrap items-center gap-3">
          <div className="bg-light inline-flex shrink-0 rounded-lg p-0.5" role="group" aria-label="Period">
            {(['week', 'month'] as const).map((p) => (
              <button
                key={p}
                type="button"
                aria-pressed={filters.period === p}
                onClick={() => go({ period: p })}
                className={cn('rounded-md px-3 py-1 text-sm', filters.period === p ? 'bg-card text-default-900 font-semibold shadow-sm' : 'text-default-500')}
              >
                {p === 'week' ? 'Week' : 'Month'}
              </button>
            ))}
          </div>

          <div className="flex items-center gap-1">
            <button type="button" className="btn btn-icon border-default-300 border" aria-label={`Previous ${filters.period}`} onClick={() => go({ at: previous })}>
              <Icon icon="chevron-left" />
            </button>
            <span className="text-default-900 min-w-40 text-center font-semibold">{label}</span>
            <button type="button" className="btn btn-icon border-default-300 border disabled:opacity-40" aria-label={`Next ${filters.period}`} disabled={!next} onClick={() => next && go({ at: next })}>
              <Icon icon="chevron-right" />
            </button>
          </div>

          <select
            className="form-select w-auto"
            aria-label="Recruiter"
            value={filters.recruiter_id ?? ''}
            onChange={(e) => go({ recruiter_id: e.target.value ? Number(e.target.value) : null })}
          >
            <option value="">All recruiters</option>
            {recruiters.map((r) => (
              <option key={r.id} value={r.id}>
                {r.name}
              </option>
            ))}
          </select>

          <a href={`/admin/reports/recruiter-activity/export?${exportQuery}`} className="btn border-default-300 hover:border-default-400 ms-auto border text-nowrap">
            <Icon icon="download" className="size-4" /> Export Excel
          </a>
        </div>

        <div className="border-default-300 grid grid-cols-2 border-t lg:grid-cols-4">
          <Stat
            label="Visits"
            value={String(summary.visits)}
            note={`${summary.previous_visits} the ${filters.period} before${change !== 0 ? ` (${change > 0 ? '+' : ''}${change})` : ''}`}
          />
          <Stat label="Hotels visited" value={`${summary.hotels_visited} of ${summary.hotels_assigned}`} note="Hotels with a recruiter assigned" />
          <Stat label="Average time on site" value={fmtMinutes(summary.avg_minutes)} note="Closed visits only" />
          <Stat
            label="Need a look"
            value={String(needLook)}
            valueClass={needLook ? 'text-warning' : undefined}
            note={`${summary.off_site} off-site · ${summary.late} closed late · ${summary.open_now} open now`}
          />
        </div>
      </div>

      <div className="card mt-5">
        <div className="card-header">
          <div>
            <h4 className="card-title">By recruiter</h4>
            <p className="text-default-500 mt-1 text-sm">Each number opens those visits in Field Visits.</p>
          </div>
        </div>
        <div className="table-wrapper">
          <table className="table">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Recruiter</th>
                <th className="text-end">Visits</th>
                <th className="text-end">Hotels visited</th>
                <th className="text-end">Not visited {staleDays}+ days</th>
                <th className="text-end">Avg time on site</th>
                <th className="text-end">Off-site</th>
                <th className="text-end">Closed late</th>
                <th className="text-end">Covering</th>
                <th className="text-end">Open now</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={9} className="text-default-500 py-6 text-center">
                    No recruiters yet.
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.id}>
                    <td className="text-default-900 font-semibold whitespace-nowrap">
                      <Link href={`/admin/people/${r.id}`} className="hover:text-primary">
                        {r.name}
                      </Link>
                    </td>
                    <td className="text-end tabular-nums">
                      <VisitsLink n={r.visits} href={visitsHref({ recruiter_id: String(r.id) })} />
                    </td>
                    <td className="text-end tabular-nums whitespace-nowrap">
                      {r.hotels_visited} of {r.hotels_assigned}
                    </td>
                    <td className={cn('text-end tabular-nums', r.not_visited > 0 && 'text-warning font-semibold')}>{r.not_visited}</td>
                    <td className="text-end tabular-nums whitespace-nowrap">{fmtMinutes(r.avg_minutes)}</td>
                    <td className="text-end tabular-nums">
                      <VisitsLink n={r.off_site} warn href={visitsHref({ recruiter_id: String(r.id), flag: 'off_site' })} />
                    </td>
                    <td className="text-end tabular-nums">
                      <VisitsLink n={r.late} warn href={visitsHref({ recruiter_id: String(r.id), flag: 'late' })} />
                    </td>
                    <td className="text-end tabular-nums">
                      <VisitsLink n={r.covering} href={visitsHref({ recruiter_id: String(r.id), flag: 'covering' })} />
                    </td>
                    <td className="text-end tabular-nums">
                      <VisitsLink n={r.open_now} warn href={`/admin/field-visits?flag=open&recruiter_id=${r.id}`} />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="mt-5 grid gap-5 xl:grid-cols-2">
        <div className="card">
          <div className="card-header">
            <div>
              <h4 className="card-title">{filters.period === 'week' ? 'Day by day' : 'Week by week'}</h4>
              <p className="text-default-500 mt-1 text-sm">Visits per recruiter{filters.period === 'month' ? ', weeks starting Monday' : ''}. Empty ones stand out.</p>
            </div>
          </div>
          <div className="table-wrapper">
            <table className="table">
              <thead className="thead-sm">
                <tr className="bg-light/25 text-xs uppercase">
                  <th>Recruiter</th>
                  {weeks.map((w) => (
                    <th key={w} className="text-end whitespace-nowrap">
                      {w}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id}>
                    <td className="text-default-900 font-medium whitespace-nowrap">{r.name}</td>
                    {r.by_week.map((n, i) => (
                      <td key={i} className="text-end">
                        <span
                          className={cn(
                            'inline-flex min-w-8 justify-center rounded-md px-1.5 py-0.5 font-semibold tabular-nums',
                            n === 0 ? (filters.period === 'month' ? 'bg-warning/15 text-warning' : 'text-default-500') : n >= 3 ? 'bg-primary/20 text-default-900' : 'bg-primary/10 text-default-900',
                          )}
                        >
                          {n}
                        </span>
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card">
          <div className="card-header flex items-start justify-between gap-3">
            <div>
              <h4 className="card-title">Not visited in {staleDays}+ days</h4>
              <p className="text-default-500 mt-1 text-sm">As of today, whatever {filters.period} is picked above.</p>
            </div>
            {stale.length > 0 && <span className="badge badge-label bg-warning/15 text-warning shrink-0">{stale.length} hotels</span>}
          </div>
          {stale.length === 0 ? (
            <div className="card-body">
              <p className="text-default-500 flex items-center gap-2">
                <Icon icon="circle-check" className="text-success size-5" /> Every hotel has had a visit in the last {staleDays} days.
              </p>
            </div>
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead className="thead-sm">
                  <tr className="bg-light/25 text-xs uppercase">
                    <th>Hotel</th>
                    <th>Recruiter</th>
                    <th>Last visit</th>
                    <th className="text-end">Days</th>
                  </tr>
                </thead>
                <tbody>
                  {stale.map((s) => (
                    <tr key={s.id}>
                      <td className="font-semibold whitespace-nowrap">
                        <Link href={`/admin/properties/${s.id}#visits`} className="text-default-900 hover:text-primary">
                          {s.hotel}
                        </Link>
                      </td>
                      <td>{s.recruiters}</td>
                      <td className="whitespace-nowrap">{s.last ?? 'Never'}</td>
                      <td className="text-end">
                        <span className="badge badge-label bg-warning/15 text-warning tabular-nums">{s.days ?? '—'}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </>
  )
}

export default Page
