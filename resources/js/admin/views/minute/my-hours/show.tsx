import PageBreadcrumb from '@/components/PageBreadcrumb'
import { hrs, money, monthDay, Pill, weekday, weekRange } from '@/components/timesheet/PmTimesheet'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'
import { clockSpan, hours1, WeekStatusPill, type HoursWeek } from './shared'

type Punch = { work_order_id: number; in: string | null; out: string | null; minutes: number; training: boolean; how: string | null }
type Day = { date: string; punches: Punch[]; minutes: number }
type Report = { id: number; status: string; message: string; reply: string | null; sent_at: string | null }

type Props = {
  week: HoursWeek
  days: Day[]
  reports: Report[]
  recruiter: string | null
  can_report: boolean
}

const NOTE: Record<HoursWeek['status'], (hotel: string) => string> = {
  in_progress: () => 'This week isn’t over yet. Hours show here as you clock in and out.',
  waiting: (hotel) => `${hotel} hasn’t approved this week yet, so the hours may still change.`,
  approved: () => 'The hotel approved this week. These hours are final.',
}

const ReportStatus = ({ report, recruiter }: { report: Report; recruiter: string | null }) => {
  if (report.status === 'completed') return <Pill tone="success">Resolved</Pill>
  if (report.status === 'rejected') return <Pill tone="info">Checked, no change needed</Pill>
  if (report.status === 'cancelled') return <Pill tone="muted">Cancelled</Pill>
  return <Pill tone="warning">{`With ${recruiter ?? 'the office'}`}</Pill>
}

const Page = ({ week, days, reports, recruiter, can_report }: Props) => {
  const many = week.jobs.length > 1
  const label = (id: number) => week.jobs.find((j) => j.work_order_id === id)?.label ?? 'Job'
  const hotel = many ? 'The hotel' : (week.jobs[0]?.label.split(' · ')[1] ?? 'The hotel')
  const [reporting, setReporting] = useState(false)
  const form = useForm<{ message: string; work_order_id: number | '' }>({ message: '', work_order_id: '' })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post(`/my-hours/${week.start}/report`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset()
        setReporting(false)
      },
    })
  }

  return (
    <>
      <Head title={`My hours: ${weekRange(week.start, week.end)}`} />
      <Link href="/my-hours" className="text-primary -mb-2 inline-flex min-h-11 items-center gap-1 pt-3 font-semibold">
        <Icon icon="chevron-left" />
        My hours
      </Link>
      <PageBreadcrumb title={weekRange(week.start, week.end)} subtitle="My hours" />

      <div className="-mt-2 mb-4 flex flex-col gap-2">
        <span className="self-start">
          <WeekStatusPill week={week} />
        </span>
        <p className="text-default-500 m-0">{NOTE[week.status](hotel)}</p>
      </div>

      <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
        <div className="flex flex-col gap-4">
          <section className="card mb-0" aria-labelledby="pay-title">
            <div className="card-body p-5">
              <h2 id="pay-title" className="sr-only">
                Pay for this week
              </h2>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <div className="text-default-900 text-xl font-semibold tabular-nums">{hours1(week.minutes)} h</div>
                  <div className="text-default-500 text-sm">worked</div>
                </div>
                <div>
                  <div className="text-default-900 text-xl font-semibold tabular-nums">{money(week.gross)}</div>
                  <div className="text-default-500 text-sm">gross pay</div>
                </div>
              </div>
              <div className="border-default-200 mt-4 border-t pt-3 text-sm tabular-nums">
                {week.jobs.map((job) => (
                  <div key={job.work_order_id}>
                    <div className="text-default-500 pt-1 pb-1 text-xs font-semibold tracking-wide uppercase">{job.label}</div>
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
                <div className="border-default-200 text-default-900 mt-2 flex justify-between border-t pt-2 font-semibold">
                  <span>Gross pay</span>
                  <span>{money(week.gross)}</span>
                </div>
                {week.adjustments.map((a, i) => (
                  <div key={i} className="flex justify-between gap-3 py-0.5">
                    <span>{a.label}</span>
                    <span className={a.amount < 0 ? 'text-danger' : 'text-default-900'}>
                      {a.amount < 0 ? '−' : '+'}
                      {money(Math.abs(a.amount))}
                    </span>
                  </div>
                ))}
                {week.adjustments.length > 0 ? (
                  <div className="text-default-900 flex justify-between pt-1 font-semibold">
                    <span>After incentives and deductions</span>
                    <span>{money(week.after_adjustments)}</span>
                  </div>
                ) : (
                  <p className="text-default-500 mt-2 mb-0">No incentives or deductions this week.</p>
                )}
              </div>
            </div>
          </section>

          <section className="card mb-0" aria-labelledby="report-title">
            <div className="card-body p-5">
              <h2 id="report-title" className="text-default-900 mb-1 text-lg font-semibold">
                Something wrong?
              </h2>
              <p className="text-default-500 mb-3 text-sm">
                Tell {recruiter ?? 'the office'} what looks wrong. You’ll get a notification when it’s been looked into.
              </p>

              {reports.map((r) => (
                <div key={r.id} className="border-default-200 mb-3 rounded-lg border p-3 text-sm">
                  <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <ReportStatus report={r} recruiter={recruiter} />
                    {r.sent_at && <span className="text-default-500">Sent {monthDay(r.sent_at)}</span>}
                  </div>
                  <p className="text-default-900 m-0">“{r.message}”</p>
                  {r.reply && <p className="text-default-500 mt-1 mb-0">Reply: {r.reply}</p>}
                </div>
              ))}

              {can_report &&
                (reporting ? (
                  <form onSubmit={submit} className="flex flex-col gap-3">
                    {many && (
                      <div>
                        <label htmlFor="report-job" className="form-label">
                          Which job?
                        </label>
                        <select
                          id="report-job"
                          className="form-input"
                          value={form.data.work_order_id}
                          onChange={(e) => form.setData('work_order_id', e.target.value ? Number(e.target.value) : '')}>
                          <option value="">More than one, or not sure</option>
                          {week.jobs.map((j) => (
                            <option key={j.work_order_id} value={j.work_order_id}>
                              {j.label}
                            </option>
                          ))}
                        </select>
                      </div>
                    )}
                    <div>
                      <label htmlFor="report-message" className="form-label">
                        What looks wrong?
                      </label>
                      <textarea
                        id="report-message"
                        className="form-textarea"
                        rows={3}
                        required
                        value={form.data.message}
                        onChange={(e) => form.setData('message', e.target.value)}
                        placeholder="For example: I clocked out at 5:30 on Tuesday, not 5:27."
                      />
                      {form.errors.message && <p className="text-danger mt-1 text-sm">{form.errors.message}</p>}
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <button type="submit" className="btn bg-primary hover:bg-primary-hover min-h-11 font-semibold text-white" disabled={form.processing}>
                        Send to {recruiter ?? 'the office'}
                      </button>
                      <button type="button" className="btn bg-light text-default-900 min-h-11" onClick={() => setReporting(false)}>
                        Cancel
                      </button>
                    </div>
                  </form>
                ) : (
                  <button type="button" className="btn bg-primary/15 text-primary hover:bg-primary/25 min-h-11 w-full font-semibold" onClick={() => setReporting(true)}>
                    <Icon icon="message-report" />
                    Report a problem
                  </button>
                ))}
            </div>
          </section>
        </div>

        <section className="card mb-0 overflow-hidden lg:col-span-2" aria-labelledby="days-title">
          <div className="flex items-baseline justify-between gap-3 px-5 pt-4 pb-2">
            <h2 id="days-title" className="text-default-900 text-lg font-semibold">
              Day by day
            </h2>
            <span className="text-default-500 text-sm">Hotel time</span>
          </div>
          {days.map((d) => (
            <div key={d.date} className="border-default-200 grid grid-cols-[3.25rem_minmax(0,1fr)_auto] items-start gap-2 border-t px-5 py-3 sm:grid-cols-[4rem_minmax(0,1fr)_auto] sm:gap-3">
              <div>
                <div className="text-default-900 font-semibold">{weekday(d.date)}</div>
                <div className="text-default-500 text-sm">{monthDay(d.date)}</div>
              </div>
              <div className="flex min-w-0 flex-col gap-1 tabular-nums">
                {d.punches.length ? (
                  d.punches.map((p, i) => (
                    <div key={i} className="flex flex-wrap items-baseline gap-x-2">
                      <span className="whitespace-nowrap">
                        {p.in} – {p.out ?? 'on the clock'}
                      </span>
                      {many && <span className="text-default-500 text-sm">{label(p.work_order_id)}</span>}
                      {p.training && <span className="text-default-500 text-sm">Training</span>}
                      {p.how && <span className="text-default-500 text-sm">{p.how}</span>}
                    </div>
                  ))
                ) : (
                  <span className="text-default-500">Not worked</span>
                )}
              </div>
              <strong className="text-default-900 tabular-nums">{d.minutes > 0 ? clockSpan(d.minutes) : '—'}</strong>
            </div>
          ))}
          <div className="border-default-200 bg-default-50 grid grid-cols-[3.25rem_minmax(0,1fr)_auto] items-start gap-2 border-t px-5 py-3 sm:grid-cols-[4rem_minmax(0,1fr)_auto] sm:gap-3">
            <div className="text-default-900 font-semibold">Week</div>
            <div className="text-default-500 text-sm">{many ? `${week.jobs.length} jobs, each with its own 40 hours before overtime` : 'All at one job'}</div>
            <strong className="text-default-900 tabular-nums">{clockSpan(days.reduce((t, d) => t + d.minutes, 0))}</strong>
          </div>
        </section>
      </div>
    </>
  )
}

export default Page
