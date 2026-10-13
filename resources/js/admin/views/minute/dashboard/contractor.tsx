import DashboardHeader from '@/components/dashboard/DashboardHeader'
import { hrs, money, monthDay, Pill, weekday, weekRange } from '@/components/timesheet/PmTimesheet'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'
import { ReactNode, useState } from 'react'

type Job = {
  id: number
  label: string
  position: string | null
  property: string | null
  city: string | null
  since: string
  pay_rate: number
  ot_pay_rate: number
  temporary: boolean
}
type PayLine = { label: string; minutes: number; rate: number; amount: number }
type PayWeek = {
  start: string
  end: string
  minutes: number
  gross: number
  jobs: { label: string; lines: PayLine[]; total: number }[]
  adjustments: { label: string; amount: number }[]
  after_adjustments: number
  may_change: boolean
}
type Charge = { reason: string | null; total: number; per_payment: number; payments: number; paid_payments: number; remaining: number }

type Props = {
  greeting: string
  jobs: Job[]
  right_now: { on_the_clock: boolean; job?: string | null; property: string | null; at: string | null; minutes: number }
  this_week: { start: string; end: string; today: string; days: { date: string; by_job: Record<string, number> }[]; total_by_job: Record<string, number> }
  pay_weeks: PayWeek[]
  charges: Charge[]
  recruiter: string | null
  details: { email: string | null; phone: string | null }
  help: { slug: string; title: string }[]
}

/** A fixed scale, so an 8-hour day is always the same height from week to week. */
const SCALE_MINUTES = 12 * 60
const GUIDE_MINUTES = 8 * 60

/** "9.9", or "—" when there are no hours. One decimal fits seven columns on a phone. */
const dayHours = (minutes: number) => (minutes > 0 ? (minutes / 60).toFixed(1) : '—')

/** One color per job, so a day's bar and the legend match. */
const JOB_COLORS = ['bg-primary', 'bg-secondary', 'bg-info', 'bg-purple']

const duration = (minutes: number) => {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h ? `${h} h ${m} m` : `${m} m`
}

/** "Jun 15 · 4 months" */
const since = (date: string) => {
  const days = Math.max(0, Math.floor((Date.now() - new Date(date + 'T12:00:00').getTime()) / 86_400_000))
  const span = days < 14 ? `${days} ${days === 1 ? 'day' : 'days'}` : days < 60 ? `${Math.floor(days / 7)} weeks` : days < 730 ? `${Math.floor(days / 30.4)} months` : `${Math.floor(days / 365)} years`
  const year = date.slice(0, 4) !== String(new Date().getFullYear()) ? `, ${date.slice(0, 4)}` : ''
  return `${monthDay(date)}${year} · ${span}`
}

const Card = ({ title, aside, children, className }: { title: string; aside?: ReactNode; children: ReactNode; className?: string }) => (
  <section className={cn('card mb-0', className)} aria-label={title}>
    <div className="card-body p-5">
      <div className="mb-3 flex items-baseline justify-between gap-3">
        <h2 className="text-default-900 text-lg font-semibold">{title}</h2>
        {aside && <span className="text-default-500 text-sm">{aside}</span>}
      </div>
      {children}
    </div>
  </section>
)

const Row = ({ label, children }: { label: string; children: ReactNode }) => (
  <div className="flex justify-between gap-4 py-1">
    <span className="text-default-500">{label}</span>
    <span className="text-default-900 text-end font-medium">{children}</span>
  </div>
)

const Page = ({ greeting, jobs, right_now: now, this_week: week, pay_weeks, charges, recruiter, details, help }: Props) => {
  const [open, setOpen] = useState<string | null>(pay_weeks[0]?.start ?? null)
  const many = jobs.length > 1
  const color = (jobId: number | string) => JOB_COLORS[Math.max(0, jobs.findIndex((j) => String(j.id) === String(jobId))) % JOB_COLORS.length]
  const dayTotals = week.days.map((d) => Object.values(d.by_job).reduce((a, b) => a + b, 0))
  const weekTotal = dayTotals.reduce((a, b) => a + b, 0)
  const places = [...new Set(jobs.map((j) => j.property).filter(Boolean))]

  const subtitle = jobs.length
    ? many
      ? jobs.map((j) => j.label).join(' and ')
      : `${jobs[0].position} at ${jobs[0].property}`
    : 'No active jobs right now'

  return (
    <>
      <DashboardHeader greeting={greeting} subtitle={subtitle} />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Same height as This week beside it; the status sits in the middle. */}
        <section className="card mb-0 h-full" aria-label="Right now">
          <div className="card-body flex h-full flex-col justify-center gap-1.5 p-5">
            {now.on_the_clock ? (
              <>
                <span className="self-start">
                  <Pill tone="success">On the clock</Pill>
                </span>
                <span className="text-default-900 text-2xl font-semibold">Since {now.at}</span>
                <span className="text-default-500">
                  {many && now.job ? `${now.job} · ` : now.property ? `At ${now.property} · ` : ''}
                  {duration(now.minutes)} so far · clock out with the QR code there
                </span>
              </>
            ) : (
              <>
                <span className="self-start">
                  <Pill tone="muted">Not on the clock</Pill>
                </span>
                <span className="text-default-900 text-2xl font-semibold">{now.at ? `Last out ${now.at}` : 'No punches yet'}</span>
                <span className="text-default-500">
                  Scan the QR code at {places.length === 1 ? places[0] : 'the hotel you are working at'} to clock in
                </span>
              </>
            )}
          </div>
        </section>

        <Card title="This week" aside={weekRange(week.start, week.end)} className="h-full">
          {/* Bars on their own row, so the 8-hour line is measured from the same baseline. */}
          <div className="relative h-20">
            <div
              className="border-default-300 pointer-events-none absolute inset-x-0 border-t border-dashed"
              style={{ bottom: `calc(2px + (100% - 2px) * ${GUIDE_MINUTES / SCALE_MINUTES})` }}
              aria-hidden="true">
              <span className="text-default-500 bg-card absolute -top-2 right-0 ps-1 text-[11px] leading-none">8 h</span>
            </div>
            <div className="relative grid h-full grid-cols-7 gap-1.5">
              {week.days.map((d, i) => (
                <div key={d.date} className="flex justify-center">
                  <div
                    className="border-default-300 flex h-full w-full max-w-8 flex-col justify-end border-b-2"
                    role="img"
                    aria-label={`${weekday(d.date)}: ${dayTotals[i] ? `${hrs(dayTotals[i])} hours` : 'no hours'}`}>
                    {Object.entries(d.by_job).map(([jobId, minutes], j, all) => (
                      <span
                        key={jobId}
                        className={cn('block w-full', color(jobId), j === all.length - 1 && 'rounded-t')}
                        style={{ height: `${Math.min(100, (minutes / SCALE_MINUTES) * 100)}%`, order: all.length - j }}
                      />
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div className="mt-1.5 grid grid-cols-7 gap-1.5">
            {week.days.map((d, i) => {
              const isToday = d.date === week.today
              return (
                <div key={d.date} className="flex flex-col items-center">
                  <span className={cn('text-sm tabular-nums', dayTotals[i] ? 'text-default-900 font-semibold' : 'text-default-500')}>{dayHours(dayTotals[i])}</span>
                  <span className={cn('text-xs', isToday ? 'text-default-900 font-bold' : 'text-default-500')}>{isToday ? 'Today' : weekday(d.date)}</span>
                </div>
              )
            })}
          </div>
          {many && (
            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
              {jobs.map((j) => (
                <span key={j.id} className="inline-flex items-center gap-1.5">
                  <span className={cn('size-2.5 rounded-sm', color(j.id))} aria-hidden="true" />
                  {j.label}
                  <span className="text-default-500 tabular-nums">· {hrs(week.total_by_job[j.id] ?? 0)} h</span>
                </span>
              ))}
            </div>
          )}
          <div className="border-default-200 mt-3 flex justify-between border-t pt-3">
            <span>Hours so far</span>
            <strong className="text-default-900 tabular-nums">{hrs(weekTotal)}</strong>
          </div>
        </Card>
      </div>

      {/* Two columns that stack on their own, so the long pay list leaves no gaps. */}
      <div className="mt-4 grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
        <Card title="Your pay by week" aside="Before taxes">
          <p className="text-default-500 mb-1 text-sm">
            Tap a week to see how it adds up.{many && ' Each job counts its own 40 hours before overtime.'}
          </p>
          {pay_weeks.length ? (
            pay_weeks.map((w) => {
              const isOpen = open === w.start
              const total = w.adjustments.length ? w.after_adjustments : w.gross
              return (
                <div key={w.start} className="border-default-200 border-t">
                  <button
                    type="button"
                    className="flex min-h-11 w-full items-center justify-between gap-3 py-3 text-start"
                    aria-expanded={isOpen}
                    onClick={() => setOpen(isOpen ? null : w.start)}>
                    <span className="min-w-0">
                      <span className="text-default-900 block font-semibold">{weekRange(w.start, w.end)}</span>
                      <span className="text-default-500 block text-sm">
                        {hrs(w.minutes)} h{w.may_change && ' · may still change'}
                      </span>
                    </span>
                    <span className="flex items-center gap-2">
                      <strong className="text-default-900 text-base tabular-nums">{money(total)}</strong>
                      <span className={cn('text-default-500 transition-transform', isOpen && 'rotate-90')} aria-hidden="true">
                        ›
                      </span>
                    </span>
                  </button>
                  {isOpen && (
                    <div className="pb-4 text-sm tabular-nums">
                      {w.jobs.map((job, i) => (
                        <div key={i}>
                          {(many || w.jobs.length > 1) && <div className="text-default-500 pt-2 pb-0.5 text-xs font-semibold tracking-wide uppercase">{job.label}</div>}
                          {job.lines.map((l) => (
                            <div key={l.label} className="flex justify-between gap-3 py-0.5">
                              <span>
                                {l.label} · {hrs(l.minutes)} h × {money(l.rate)}
                              </span>
                              <span className="text-default-900">{money(l.amount)}</span>
                            </div>
                          ))}
                        </div>
                      ))}
                      <div className="border-default-200 text-default-900 mt-1.5 flex justify-between border-t pt-2 font-semibold">
                        <span>Gross pay</span>
                        <span>{money(w.gross)}</span>
                      </div>
                      {w.adjustments.map((a, i) => (
                        <div key={i} className="flex justify-between gap-3 py-0.5">
                          <span>{a.label}</span>
                          <span className={a.amount < 0 ? 'text-danger' : 'text-default-900'}>
                            {a.amount < 0 ? '−' : '+'}
                            {money(Math.abs(a.amount))}
                          </span>
                        </div>
                      ))}
                      {w.adjustments.length > 0 && (
                        <div className="text-default-900 flex justify-between pt-1 font-semibold">
                          <span>After adjustments</span>
                          <span>{money(w.after_adjustments)}</span>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )
            })
          ) : (
            <p className="text-default-500 py-3">Your pay shows here after your first full week.</p>
          )}
          <Link href="/my-hours" className="border-default-200 text-primary flex min-h-11 items-center justify-between border-t pt-3 font-semibold">
            See all weeks and day-by-day hours
            <Icon icon="chevron-right" />
          </Link>
        </Card>

        <div className="flex flex-col gap-4">
        <Card title="Uniforms and charges">
          {charges.length ? (
            charges.map((c, i) => (
              <div key={i} className="border-default-200 border-t py-3 first:border-0 first:pt-0">
                <div className="flex justify-between gap-3">
                  <span className="text-default-900 font-semibold">{c.reason ?? 'Charge'}</span>
                  <span className="text-default-900 tabular-nums">{money(c.remaining)} left</span>
                </div>
                <span className="text-default-500 text-sm">
                  {money(c.per_payment)} a week · {c.paid_payments} of {c.payments} paid · {money(c.total)} in all
                </span>
              </div>
            ))
          ) : (
            <p>Nothing owed. If you get uniforms or equipment that cost money, the payments taken from each check show here.</p>
          )}
        </Card>

        <Card title={many ? 'Your jobs' : 'Your job'}>
          {jobs.map((j) => (
            <div key={j.id} className="border-default-200 border-t py-2 first:border-0 first:pt-0">
              <div className="flex items-center justify-between gap-3 pb-1">
                <strong className="text-default-900">{j.label}</strong>
                {j.temporary && <Pill tone="warning">Temporary</Pill>}
              </div>
              {j.city && <Row label="Where">{`${j.property}, ${j.city}`}</Row>}
              <Row label="Since">{since(j.since)}</Row>
              <Row label="Pay rate">{`${money(j.pay_rate)} / h · ${money(j.ot_pay_rate)} overtime`}</Row>
            </div>
          ))}
          {recruiter && (
            <div className="border-default-200 mt-1 border-t pt-2">
              <Row label="Your recruiter">{recruiter}</Row>
            </div>
          )}
        </Card>

        <Card title="Your details">
          <Row label="Email">{details.email ?? '—'}</Row>
          <Row label="Phone">{details.phone ?? '—'}</Row>
          <p className="text-default-500 mt-2 text-sm">Your phone number is how you clock in, so keep it current.</p>
          {/* The page's one action, so it gets the main button style. */}
          <Link href="/my-info" className="btn bg-primary hover:bg-primary-hover mt-3 w-full px-4 font-semibold text-white sm:w-auto">
            <Icon icon="edit" className="size-4" /> Request a change
          </Link>
        </Card>

        {help.length > 0 && (
          <Card title="Help">
            {help.map((a) => (
              <Link key={a.slug} href={`/kb/${a.slug}`} className="border-default-200 text-default-900 hover:text-primary flex min-h-11 items-center justify-between border-t first:border-0">
                {a.title}
                <span className="text-default-500" aria-hidden="true">
                  ›
                </span>
              </Link>
            ))}
            <Link href="/kb" className="text-primary mt-2 inline-block font-medium">
              All help articles →
            </Link>
          </Card>
        )}
        </div>
      </div>
    </>
  )
}

export default Page
