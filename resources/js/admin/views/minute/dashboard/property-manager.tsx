import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import { hrs, money, Pill } from '@/components/timesheet/PmTimesheet'
import { hasPermission } from '@/layouts/components/useMenuItems'
import { cn } from '@/utils/helpers'
import { Link, usePage } from '@inertiajs/react'

type Waiting = {
  id: number
  property: string | null
  week: string
  sent_at: string | null
  sent_by: string | null
  waiting: string | null
  people: number
  total_minutes: number
  overtime_minutes: number
  amount: number
}
type NextUp = { property: string; week: string; declined: boolean; recruiter: string | null }
type Request = { kind: 'staffing' | 'pay_increase'; title: string; property: string | null; needed_by: string | null; asked: string | null; progress: string; overdue: boolean; with: string | null }
type DirectHire = { name: string | null; position: string | null; property: string | null; worked: number; threshold: number; remaining: number; percent: number; eligible: boolean }
type Week = { label: string; straight_minutes: number; overtime_minutes: number; current: boolean }

type Props = {
  greeting: string
  scope: string
  today: string
  many_properties: boolean
  waiting: Waiting[]
  next_up: NextUp[]
  this_week: { week: string | null; total_minutes: number; on_the_clock: { name: string | null; property: string | null; since: string | null }[]; href: string }
  invoices: {
    open_total: number
    open_count: number
    overdue_total: number
    overdue_count: number
    rows: { id: number; number: string; week: string | null; due: string; days_overdue: number | null; total: number }[]
  }
  requests: Request[]
  direct_hire: { rows: DirectHire[]; contractors: number }
  hours: Week[]
  schedules: { id: number; name: string; weeks: { week_start: string; label: string; when: string; files: number }[] }[] | null
}

const count = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`
const wholeHours = (min: number) => Math.round(min / 60).toLocaleString('en-US')

const Figure = ({ label, value, note, valueClass }: { label: string; value: string; note?: string; valueClass?: string }) => (
  <div>
    <div className="text-default-500 text-[11px] font-medium tracking-wider uppercase">{label}</div>
    <div className={cn('text-default-900 text-xl font-semibold tabular-nums', valueClass)}>{value}</div>
    {note && <div className="text-default-500 text-xs">{note}</div>}
  </div>
)

const Page = (props: Props) => {
  const { greeting, scope, today, many_properties: many, waiting, next_up, this_week, invoices, requests, direct_hire, hours, schedules } = props
  const permissions = (usePage().props as { auth?: { permissions?: string[] } }).auth?.permissions ?? []
  const peak = Math.max(1, ...hours.map((w) => w.straight_minutes + w.overtime_minutes))

  return (
    <>
      <DashboardHeader
        greeting={greeting}
        subtitle={`${scope} · ${today}`}
        aside={
          <div className="flex flex-wrap gap-2">
            {hasPermission('workflows.pay_increase.initiate', permissions) && (
              <Link href="/pay-increases?new=1" className="btn btn-light px-4">
                Request a pay increase
              </Link>
            )}
            {hasPermission('workflows.more_staff.initiate', permissions) && (
              <Link href="/staffing-requests?new=1" className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white">
                Request staff
              </Link>
            )}
          </div>
        }
      />

      <div className="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <DashCard
          className="lg:col-span-2"
          title="Waiting for your approval"
          description="Approving a week creates its invoice"
          footer={
            next_up.length > 0 ? (
              <span className="text-default-500">
                Next:{' '}
                {next_up.map((n, i) => (
                  <span key={`${n.property}-${n.week}`}>
                    {i > 0 && ' · '}
                    <strong className="text-default-900">{n.week}</strong>
                    {many && ` at ${n.property}`}
                    {n.declined ? ` is back with ${n.recruiter ?? 'your recruiter'} to fix` : ` is with ${n.recruiter ?? 'your recruiter'} for review`}
                  </span>
                ))}
                .
              </span>
            ) : undefined
          }>
          {waiting.length ? (
            waiting.map((w) => (
              // Wraps rather than a DashRow: on a phone the button drops under the week.
              <div key={w.id} className="border-default-200 flex flex-wrap items-center gap-4 border-b border-dashed py-3 last:border-0">
                <div className="min-w-0 flex-[1_1_320px]">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                  {w.waiting && <Pill tone="warning">{`Waiting ${w.waiting}`}</Pill>}
                  {w.sent_at && (
                    <span className="text-default-500 text-sm">
                      Sent {w.sent_at}
                      {w.sent_by && ` by ${w.sent_by}`}
                    </span>
                  )}
                </div>
                <div className="text-default-900 mt-2 mb-1 text-lg font-semibold">
                  Week of {w.week}
                  {many && <span className="text-default-500 font-normal"> · {w.property}</span>}
                </div>
                <div className="flex flex-wrap gap-x-5 gap-y-1">
                  <span>
                    <strong className="text-default-900">{w.people}</strong> {w.people === 1 ? 'person' : 'people'}
                  </span>
                  <span>
                    <strong className="text-default-900">{hrs(w.total_minutes)}</strong> hours
                  </span>
                  {w.overtime_minutes > 0 && (
                    <span>
                      <strong className="text-default-900">{hrs(w.overtime_minutes)}</strong> overtime
                    </span>
                  )}
                  <span>
                    <strong className="text-default-900">{money(w.amount)}</strong> before tax
                  </span>
                </div>
                </div>
                <Link href={`/timesheets/${w.id}`} className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white">
                  Review and approve
                </Link>
              </div>
            ))
          ) : (
            <div className="flex items-center gap-3 py-4">
              <Pill tone="success">All caught up</Pill>
              <span>Nothing to approve right now.</span>
            </div>
          )}
        </DashCard>

        <DashCard title="This week" description={this_week.week ? `${this_week.week} · updates as people clock in` : undefined} footer={<ViewAll href={this_week.href}>View this week</ViewAll>}>
          <div className="flex gap-8 py-2">
            <Figure label="Hours so far" value={hrs(this_week.total_minutes)} />
            <Figure label="On the clock" value={String(this_week.on_the_clock.length)} />
          </div>
          {this_week.on_the_clock.length ? (
            this_week.on_the_clock.slice(0, 5).map((p, i) => (
              <DashRow key={i} end={<span className="text-default-500 text-sm whitespace-nowrap">in since {p.since}</span>}>
                <span className="text-default-900 font-medium">{p.name}</span>
                {many && <span className="text-default-500 block text-xs">{p.property}</span>}
              </DashRow>
            ))
          ) : (
            <DashEmpty>Nobody is on the clock right now.</DashEmpty>
          )}
        </DashCard>
      </div>

      <div className={cn('mb-5 grid grid-cols-1 gap-5', schedules ? 'lg:grid-cols-3' : 'lg:grid-cols-2')}>
        {schedules && <SchedulesCard hotels={schedules} />}

        <DashCard title="Invoices" description="What you owe and when it's due" footer={<ViewAll href="/invoices">All invoices</ViewAll>}>
          <div className="flex gap-10 py-2">
            <Figure label="Open balance" value={money(invoices.open_total)} note={invoices.open_count ? count(invoices.open_count, 'invoice', 'invoices') : 'Nothing to pay'} />
            <Figure
              label="Overdue"
              value={invoices.overdue_count ? money(invoices.overdue_total) : 'None'}
              valueClass={invoices.overdue_count ? 'text-danger' : 'text-success'}
              note={invoices.overdue_count ? count(invoices.overdue_count, 'invoice', 'invoices') : 'Nothing past due'}
            />
          </div>
          {invoices.rows.map((i) => (
            <DashRow
              key={i.id}
              end={
                <>
                  <Pill tone={i.days_overdue !== null ? 'danger' : 'info'}>{i.days_overdue !== null ? `Overdue · ${count(i.days_overdue, 'day', 'days')}` : `Due ${i.due}`}</Pill>
                  <span className="text-default-900 min-w-24 font-semibold tabular-nums">{money(i.total)}</span>
                </>
              }>
              <Link href={`/invoices/${i.id}`} className="text-default-900 hover:text-primary font-semibold">
                {i.number}
              </Link>
              {i.week && <span className="text-default-500 block text-xs">Week of {i.week}</span>}
            </DashRow>
          ))}
        </DashCard>

        <DashCard
          title="Your requests"
          description="Staff and pay increases you've asked for"
          footer={
            <span className="flex flex-wrap gap-x-6 gap-y-1">
              <ViewAll href="/staffing-requests">Staffing requests</ViewAll>
              <ViewAll href="/pay-increases">Pay increases</ViewAll>
            </span>
          }>
          {requests.length ? (
            requests.map((r, i) => (
              <DashRow
                key={i}
                top
                end={
                  <span className="flex flex-col items-end gap-1">
                    <Pill tone={r.overdue ? 'danger' : 'warning'}>{r.progress}</Pill>
                    {r.with && <span className="text-default-500 text-xs">With {r.with}</span>}
                  </span>
                }>
                <span className="text-default-900 font-semibold">{r.title}</span>
                <span className="text-default-500 block text-xs">
                  {r.kind === 'staffing' ? 'Staffing' : 'Pay increase'}
                  {many && r.property && ` · ${r.property}`}
                  {r.needed_by && ` · needed by ${r.needed_by}`}
                  {r.asked && ` · asked ${r.asked}`}
                </span>
              </DashRow>
            ))
          ) : (
            <DashEmpty>No open requests. Use Request staff or Request a pay increase above.</DashEmpty>
          )}
        </DashCard>
      </div>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <DashCard
          title="Closest to direct hire"
          description={direct_hire.rows[0] ? `Hours worked here, out of ${direct_hire.rows[0].threshold.toLocaleString('en-US')}, before you can hire them directly` : 'Hours worked here before you can hire them directly'}
          footer={<ViewAll href="/contractors">{`All ${count(direct_hire.contractors, 'contractor', 'contractors')}`}</ViewAll>}>
          {direct_hire.rows.length ? (
            direct_hire.rows.map((d, i) => (
              <DashRow
                key={i}
                end={
                  <span className="w-28 text-end text-sm tabular-nums">
                    <strong className="text-default-900">{Math.round(d.worked).toLocaleString('en-US')}</strong> h
                    <span className="text-default-500 block text-xs">{d.eligible ? 'Eligible now' : `${Math.round(d.remaining).toLocaleString('en-US')} to go`}</span>
                  </span>
                }>
                <div className="flex items-center gap-4">
                  <span className="w-40 min-w-0 shrink-0">
                    <span className="text-default-900 block truncate font-semibold">{d.name}</span>
                    <span className="text-default-500 block truncate text-xs">
                      {d.position}
                      {many && d.property && ` · ${d.property}`}
                    </span>
                  </span>
                  <span className="bg-default-200 h-2 min-w-16 flex-1 overflow-hidden rounded-full" aria-hidden="true">
                    <span className={cn('block h-full rounded-full', d.eligible ? 'bg-success' : 'bg-primary')} style={{ width: `${d.percent}%` }} />
                  </span>
                </div>
              </DashRow>
            ))
          ) : (
            <DashEmpty>No contractors are placed with you yet.</DashEmpty>
          )}
        </DashCard>

        <DashCard title="Hours per week" description="Last 8 weeks · overtime on top">
          <div className="flex h-56 items-end gap-3 py-3">
            {hours.map((w) => {
              const total = w.straight_minutes + w.overtime_minutes
              return (
                <div key={w.label} className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5">
                  <span className="text-default-900 text-xs font-semibold tabular-nums">{total ? wholeHours(total) : '—'}</span>
                  <div
                    className="flex w-full max-w-10 flex-col justify-end"
                    style={{ height: `${(total / peak) * 75}%` }}
                    role="img"
                    aria-label={`Week of ${w.label}: ${hrs(total)} hours, ${hrs(w.overtime_minutes)} overtime${w.current ? ', so far' : ''}`}>
                    {w.overtime_minutes > 0 && <span className="bg-secondary block rounded-t" style={{ flexGrow: w.overtime_minutes }} />}
                    <span className={cn('block', w.current ? 'bg-primary/35' : 'bg-primary', w.overtime_minutes ? '' : 'rounded-t')} style={{ flexGrow: w.straight_minutes }} />
                  </div>
                  <span className={cn('text-xs whitespace-nowrap', w.current ? 'text-default-900 font-semibold' : 'text-default-500')}>{w.current ? 'This wk' : w.label}</span>
                </div>
              )
            })}
          </div>
        </DashCard>
      </div>
    </>
  )
}

/**
 * Is the hotel's schedule up for this week and the next two? One hotel: the
 * three weeks. Several: next week at each hotel.
 */
const SchedulesCard = ({ hotels }: { hotels: NonNullable<Props['schedules']> }) => {
  const rows =
    hotels.length === 1
      ? hotels[0].weeks.map((w) => ({ key: w.week_start, title: `${w.when} · ${w.label}`, week: w, property: hotels[0].id }))
      : hotels.map((h) => ({ key: String(h.id), title: h.name, week: h.weeks[1], property: h.id }))

  return (
    <DashCard title="Schedules" description="Your recruiter plans staff from these" footer={<ViewAll href="/schedules">All weeks</ViewAll>}>
      {rows.map((r) => (
        <DashRow
          key={r.key}
          end={
            r.week.files > 0 ? (
              <Pill tone="success">{count(r.week.files, 'file', 'files')}</Pill>
            ) : r.week.when === 'In 2 weeks' ? (
              <Link href={`/schedules?property=${r.property}`} className="text-primary text-sm font-medium">
                Upload early
              </Link>
            ) : (
              <Link href={`/schedules?property=${r.property}`} className="btn btn-sm bg-primary hover:bg-primary-hover font-semibold text-white">
                Upload
              </Link>
            )
          }>
          <span className="text-default-900 font-semibold">{r.title}</span>
          <span className="text-default-500 block text-xs">{r.week.files > 0 ? 'Uploaded' : hotels.length > 1 ? `Next week · ${r.week.label} · not uploaded yet` : 'Not uploaded yet'}</span>
        </DashRow>
      ))}
    </DashCard>
  )
}

export default Page
