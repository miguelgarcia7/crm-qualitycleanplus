import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head } from '@inertiajs/react'
import { useState } from 'react'
import ReferenceNav from './components/ReferenceNav'

type Span = { start: number; end: number | null }
type Task = {
  name: string
  source: string
  schedule_timezone: string
  cadence: string
  cadence_utc: string
  next_run: string
  next_run_utc: string
  timeline: { chicago: Span; utc: Span } | null
  evening_in_chicago: boolean
}

type Props = { tasks: Task[] }
type Zone = 'chicago' | 'utc'

const pct = (hours: number) => `${(Math.min(Math.max(hours, 0), 24) / 24) * 100}%`

const TICKS: Record<Zone, string[]> = {
  chicago: ['12 AM', '6 AM', '12 PM', '6 PM', '12 AM'],
  utc: ['00:00', '06:00', '12:00', '18:00', '24:00'],
}

const Page = ({ tasks }: Props) => {
  const [zone, setZone] = useState<Zone>('chicago')
  const chicago = zone === 'chicago'

  const evening = tasks.filter((t) => t.evening_in_chicago)
  const points = tasks.filter((t) => t.timeline && t.timeline[zone].end === null)
  const bands = tasks.filter((t) => t.timeline && t.timeline[zone].end !== null)

  return (
    <>
      <Head title="Automations" />
      <PageBreadcrumb title="System Reference" subtitle="Administration" />
      <ReferenceNav current="/admin/system/automations" />

      <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
        <p className="text-default-500 max-w-2xl">What the system does by itself, and when. Nobody has to press a button for these.</p>
        <div role="group" aria-label="Show times in" className="bg-default-100 inline-flex rounded-lg p-1">
          {(['chicago', 'utc'] as Zone[]).map((z) => (
            <button
              key={z}
              type="button"
              onClick={() => setZone(z)}
              aria-pressed={zone === z}
              className={cn('rounded-md px-3.5 py-1.5 text-sm font-semibold', zone === z ? 'bg-card text-default-900 shadow-sm' : 'text-default-500 hover:text-default-900')}
            >
              {z === 'chicago' ? 'Chicago time' : 'UTC'}
            </button>
          ))}
        </div>
      </div>

      {evening.length > 0 && (
        <div role="note" className="bg-warning/15 text-default-800 mb-5 flex items-start gap-3 rounded-lg px-4 py-3">
          <Icon icon="alert-triangle" className="text-warning mt-0.5 size-5 shrink-0" />
          <div>
            <div className="font-semibold">
              {evening.length} nightly {evening.length === 1 ? 'task is' : 'tasks are'} scheduled in UTC, so in Chicago they run in the evening.
            </div>
            <div className="text-default-600 text-sm">
              {evening.map((t) => t.cadence.replace('Daily at ', '')).join(', ')}: the evening before the date they’re for, while people may still be clocked in.
            </div>
          </div>
        </div>
      )}

      <div className="card mb-5">
        <div className="card-body">
          <div className="text-default-400 mb-3 flex justify-between text-sm">
            <span>A weekday, {chicago ? 'Chicago time' : 'UTC'}</span>
            <span>Hover a dot for its task</span>
          </div>
          <div className="bg-default-50 border-default-300 relative h-24 overflow-hidden rounded-lg border">
            {bands.map((t) => {
              const s = t.timeline![zone]
              return (
                <div
                  key={t.name}
                  title={`${chicago ? t.cadence : t.cadence_utc} · ${t.name}`}
                  className="bg-info/15 border-info/40 absolute inset-y-0 border-x"
                  style={{ left: pct(s.start), width: `calc(${pct(s.end!)} - ${pct(s.start)})` }}
                >
                  <span className="text-info block truncate px-2 py-1 text-xs font-semibold">{t.name.split(':')[0]}</span>
                </div>
              )
            })}
            {points.map((t, i) => {
              const s = t.timeline![zone]
              return (
                <span
                  key={t.name}
                  title={`${chicago ? t.cadence : t.cadence_utc} · ${t.name}`}
                  className="bg-primary ring-card absolute size-3 -translate-x-1/2 rounded-full ring-3"
                  style={{ left: pct(s.start), top: `${34 + (i % 3) * 18}px` }}
                />
              )
            })}
          </div>
          <div className="text-default-400 relative mt-1.5 h-5 text-xs tabular-nums">
            {TICKS[zone].map((label, i) => (
              <span key={i} className="absolute whitespace-nowrap" style={{ left: pct(i * 6), transform: i === 0 ? 'none' : i === 4 ? 'translateX(-100%)' : 'translateX(-50%)' }}>
                {label}
              </span>
            ))}
          </div>
        </div>
      </div>

      <div className="card mb-0 overflow-x-auto">
        <table className="w-full min-w-[760px] border-separate border-spacing-0 text-sm">
          <thead>
            <tr>
              <th scope="col" className="text-default-400 border-default-300 w-56 border-b px-5 py-3 text-start text-xs font-semibold">
                Runs
              </th>
              <th scope="col" className="text-default-400 border-default-300 border-b px-5 py-3 text-start text-xs font-semibold">
                What it does
              </th>
              <th scope="col" className="text-default-400 border-default-300 w-56 border-b px-5 py-3 text-start text-xs font-semibold">
                Next run
              </th>
            </tr>
          </thead>
          <tbody>
            {tasks.map((t) => (
              <tr key={t.name}>
                <td className="border-default-100 border-b px-5 py-3 align-top">
                  <div className="text-default-900 font-semibold tabular-nums">{chicago ? t.cadence : t.cadence_utc}</div>
                  <div className="text-default-400 text-xs">Scheduled in {t.schedule_timezone}</div>
                </td>
                <td className="border-default-100 border-b px-5 py-3 align-top">
                  <div className="text-default-800 font-medium">{t.name}</div>
                  <div className="text-default-400 font-mono text-xs">{t.source}</div>
                </td>
                <td className="text-default-600 border-default-100 border-b px-5 py-3 align-top tabular-nums">{chicago ? t.next_run : t.next_run_utc}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p className="text-default-400 mt-5 text-sm">Read from the app’s schedule. The app doesn’t record when each task last ran, so that isn’t shown.</p>
    </>
  )
}

export default Page
