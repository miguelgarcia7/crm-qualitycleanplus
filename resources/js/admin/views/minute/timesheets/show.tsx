import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Chip, hrs, isWeekend, money, monthDay, shortClock, StatusPill, weekRange, weekday } from '@/components/timesheet/PmTimesheet'
import { Stat } from '@/components/timesheet/WeekSummary'
import { cn, formatClockTime } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useEffect, useState } from 'react'

type Timesheet = {
  id: number
  status: string
  sent_at: string | null
  sent_by: string | null
  approved_at: string | null
  approved_by: string | null
  declined_at: string | null
  declined_by: string | null
  decline_reason: string | null
  decline_category: string | null
  invoice: { id: number; number: string } | null
}
type Row = { work_order_id: number; person_id: number; contractor: string | null; position: string | null }
type Entry = { id: number; work_order_id: number; date: string | null; start_time: string | null; end_time: string | null; duration_minutes: number | null }
type Summary = { regular_minutes: number; overtime_minutes: number; holiday_minutes: number; training_minutes: number; total_bill: number }
type Adjustment = { id: number; person_id: number; work_order_id: number | null; label: string; value: number }

type Props = {
  timesheet: Timesheet | null
  property: { id: number; name: string; timezone: string }
  week: { start: string; end: string; days: string[]; is_current: boolean }
  rows: Row[]
  entries: Entry[]
  summaries: Record<number, Summary>
  adjustments: Adjustment[]
  can: { decide: boolean }
}

const INVOICED = ['approved', 'invoiced', 'invoice_sent']

const todayAt = (tz: string) => new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(new Date())
const timezoneName = (tz: string) =>
  new Intl.DateTimeFormat('en-US', { timeZone: tz, timeZoneName: 'long' }).formatToParts(new Date()).find((p) => p.type === 'timeZoneName')?.value ?? tz
const initials = (name: string | null) =>
  (name ?? '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('')
const minutesOf = (list: Entry[]) => list.reduce((sum, e) => sum + (e.duration_minutes ?? 0), 0)
const said = (who: string | null, verb: string, date: string | null) =>
  `${who === 'you' ? 'You' : (who ?? 'Someone')} ${verb}${date ? ` this week on ${monthDay(date)}` : ' this week'}`

const Page = ({ timesheet, property, week, rows, entries, summaries, adjustments, can }: Props) => {
  const [mode, setMode] = useState<'hours' | 'punches'>('hours')
  const [declining, setDeclining] = useState(false)

  const status = timesheet?.status ?? 'draft'
  const invoiced = INVOICED.includes(status)
  const today = todayAt(property.timezone)
  const recruiter = timesheet?.sent_by ?? 'your recruiter'
  const title = week.is_current ? `This week · ${weekRange(week.start, week.end)}` : `Week of ${weekRange(week.start, week.end)}, ${week.end.slice(0, 4)}`

  // The week in progress refreshes as people clock in and out (Reverb).
  useEffect(() => {
    if (!week.is_current || typeof window === 'undefined' || !window.Echo) return
    const channel = window.Echo.private(`property.${property.id}`)
    channel.listen('.time-entry.saved', (e: { week_start: string }) => {
      if (e.week_start === week.start) router.reload({ only: ['entries', 'summaries', 'rows'] })
    })
    return () => window.Echo.leave(`property.${property.id}`)
  }, [property.id, week.start, week.is_current])

  const cellEntries = (wo: number, date: string) =>
    entries.filter((e) => e.work_order_id === wo && e.date === date).sort((a, b) => (a.start_time ?? '').localeCompare(b.start_time ?? ''))

  const weekOf = (r: Row) => {
    const s = summaries[r.work_order_id]
    const adj = adjustments.filter((a) => a.work_order_id === r.work_order_id).reduce((sum, a) => sum + a.value, 0)
    return {
      total: (s?.regular_minutes ?? 0) + (s?.overtime_minutes ?? 0) + (s?.holiday_minutes ?? 0) + (s?.training_minutes ?? 0),
      regular: s?.regular_minutes ?? 0,
      overtime: s?.overtime_minutes ?? 0,
      holiday: s?.holiday_minutes ?? 0,
      adjustments: adj,
      amount: (s?.total_bill ?? 0) + adj,
    }
  }
  const weeks = rows.map((r) => ({ row: r, week: weekOf(r) }))
  // Adjustments not tied to a work order still land on the invoice.
  const looseAdjustments = adjustments.filter((a) => a.work_order_id === null || !rows.some((r) => r.work_order_id === a.work_order_id))
  const looseTotal = looseAdjustments.reduce((sum, a) => sum + a.value, 0)
  const totals = weeks.reduce(
    (t, { week: w }) => ({ total: t.total + w.total, regular: t.regular + w.regular, overtime: t.overtime + w.overtime, holiday: t.holiday + w.holiday, amount: t.amount + w.amount }),
    { total: 0, regular: 0, overtime: 0, holiday: 0, amount: looseTotal },
  )
  const adjustmentTotal = adjustments.reduce((sum, a) => sum + a.value, 0)
  const people = new Set(rows.map((r) => r.person_id)).size
  const overtimeNames = [...new Set(weeks.filter((w) => w.week.overtime > 0).map((w) => w.row.contractor))]

  const approve = () => {
    if (!timesheet) return
    confirmAction({
      title: 'Approve this week',
      message: (
        <>
          Approve <strong className="text-default-900">{weekRange(week.start, week.end)}</strong> at {property.name}? This creates the invoice for{' '}
          <strong className="text-default-900">{money(totals.amount)}</strong> plus tax.
          <span className="text-default-500 mt-2 block">
            You can&apos;t change the week after approving it. If anything looks wrong, decline it instead and tell {recruiter} what to fix.
          </span>
        </>
      ),
      confirmLabel: 'Approve',
      tone: 'primary',
      onConfirm: () => router.post(`/timesheets/${timesheet.id}/approve`, {}, { preserveScroll: true }),
    })
  }

  return (
    <>
      <Head title={`${title} — ${property.name}`} />
      <PageBreadcrumb title={title} subtitle="Timesheets" />
      <p className="text-default-500 -mt-3 mb-6">
        {property.name} · times in {timezoneName(property.timezone)}
      </p>

      <div className="card mb-4">
        <div className="card-body flex flex-wrap items-center justify-between gap-4 p-4 lg:px-6">
          <StatusSteps status={status} isCurrent={week.is_current} />
          <div className="flex flex-wrap items-center gap-2">
            <Link href="/timesheets" className="btn btn-light px-4">
              All weeks
            </Link>
            {can.decide && (
              <>
                <button className="btn bg-danger/12 text-danger hover:bg-danger px-4 font-semibold hover:text-white" onClick={() => setDeclining(true)}>
                  Decline…
                </button>
                <button className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white" onClick={approve}>
                  Approve week
                </button>
              </>
            )}
            {invoiced && timesheet?.invoice && (
              <>
                <a href={`/invoices/${timesheet.invoice.id}/pdf`} className="btn btn-light px-4">
                  Download PDF
                </a>
                <Link href={`/invoices/${timesheet.invoice.id}`} className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white">
                  View invoice {timesheet.invoice.number}
                </Link>
              </>
            )}
          </div>
        </div>
      </div>

      {status === 'pending_approval' && timesheet && (
        <p className="bg-info/12 text-default-900 mb-4 rounded-lg px-4 py-3">
          <strong>{said(timesheet.sent_by, 'sent you', timesheet.sent_at)}.</strong> Approving creates the invoice for {money(totals.amount)} plus tax. If anything
          looks wrong, decline it with a note saying what to fix.
        </p>
      )}
      {status === 'declined' && timesheet && (
        <p className="bg-danger/10 text-default-900 mb-4 rounded-lg px-4 py-3">
          <strong>
            {said(timesheet.declined_by, 'declined', timesheet.declined_at)}
            {timesheet.decline_category ? ` · ${timesheet.decline_category}` : ''}.
          </strong>{' '}
          {timesheet.decline_reason && <>“{timesheet.decline_reason}” </>}
          {recruiter[0].toUpperCase() + recruiter.slice(1)} will fix it and send it again.
        </p>
      )}
      {invoiced && timesheet && (
        <p className="bg-success/12 text-default-900 mb-4 rounded-lg px-4 py-3">
          <strong>{said(timesheet.approved_by, 'approved', timesheet.approved_at)}.</strong>
          {timesheet.invoice && <> Invoice {timesheet.invoice.number} was created from these hours.</>}
        </p>
      )}
      {status === 'voided' && (
        <p className="bg-light text-default-900 mb-4 rounded-lg px-4 py-3">The invoice for this week was voided. Your recruiter will send a corrected week if one is needed.</p>
      )}

      <div className="card mb-4 grid grid-cols-1 lg:grid-cols-3">
        <Stat
          label="Hours"
          value={hrs(totals.total)}
          note={`${people} ${people === 1 ? 'person' : 'people'} · ${hrs(totals.regular)} regular${totals.holiday ? ` · ${hrs(totals.holiday)} holiday` : ''}`}
        />
        <Stat
          label="Overtime"
          value={hrs(totals.overtime)}
          note={overtimeNames.length ? overtimeNames.join(', ') + ', over 40 h' : 'Nobody over 40 h'}
        />
        <Stat
          label="Amount"
          value={money(totals.amount)}
          note={adjustmentTotal ? `Before tax · includes ${money(adjustmentTotal)} in adjustments` : 'Before tax'}
        />
      </div>

      <section className="card" aria-label="Hours by day">
        <div className="card-header flex flex-wrap items-center justify-between gap-3">
          <div className="bg-light inline-flex rounded-lg p-0.5" role="group" aria-label="Cell display">
            {(['hours', 'punches'] as const).map((m) => (
              <button
                key={m}
                aria-pressed={mode === m}
                onClick={() => setMode(m)}
                className={cn('rounded-md px-3 py-1 text-sm capitalize', mode === m ? 'bg-card text-default-900 font-semibold shadow-sm' : 'text-default-500')}>
                {m}
              </button>
            ))}
          </div>
          <span className="text-default-500 text-xs">Read only · your recruiter makes any corrections</span>
        </div>

        <div className="relative overflow-x-auto">
          <table className="w-full min-w-[1000px] table-fixed text-sm">
            <colgroup>
              <col className="w-60" />
              {week.days.map((d) => (
                <col key={d} />
              ))}
              <col className="w-28" />
              <col className="w-32" />
            </colgroup>
            <thead>
              <tr className="border-default-300 text-default-500 border-b text-xs uppercase">
                <th className="bg-card sticky left-0 z-[1] py-2.5 ps-5 text-start font-medium">Contractor</th>
                {week.days.map((d) => (
                  <th key={d} className={cn('px-1 py-2.5 text-center font-medium', isWeekend(d) && 'bg-light/30', d === today && 'text-primary')}>
                    {weekday(d)}
                    <span className={cn('block text-[13px] font-semibold normal-case', d === today ? 'text-primary' : 'text-default-900')}>{monthDay(d)}</span>
                  </th>
                ))}
                <th className="px-2 py-2.5 text-end font-medium">Week</th>
                <th className="py-2.5 ps-2 pe-5 text-end font-medium">Amount</th>
              </tr>
            </thead>
            <tbody>
              {weeks.length ? (
                weeks.map(({ row: r, week: w }) => (
                  <tr key={r.work_order_id} className="border-default-300 border-b last:border-0">
                    <td className="bg-card sticky left-0 z-[1] py-2 ps-5">
                      <div className="flex items-center gap-2.5">
                        <span className="bg-primary/12 text-primary-hover grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold" aria-hidden="true">
                          {initials(r.contractor)}
                        </span>
                        <span className="min-w-0">
                          <span className="text-default-900 block truncate font-medium">{r.contractor}</span>
                          <span className="text-default-500 block truncate text-xs">{r.position}</span>
                        </span>
                      </div>
                    </td>
                    {week.days.map((d) => (
                      <td key={d} className={cn('px-1 py-2 text-center', isWeekend(d) && 'bg-light/30')}>
                        <DayCell list={cellEntries(r.work_order_id, d)} mode={mode} />
                      </td>
                    ))}
                    <td className="px-2 py-2 text-end">
                      <div className="text-default-900 text-[15px] font-semibold tabular-nums">{hrs(w.total)}</div>
                      <div className="mt-0.5 flex flex-wrap justify-end gap-1">
                        {w.overtime > 0 && <Chip tone="secondary">{`+${hrs(w.overtime)} OT`}</Chip>}
                        {w.holiday > 0 && <Chip tone="info">{`${hrs(w.holiday)} holiday`}</Chip>}
                      </div>
                    </td>
                    <td className="py-2 ps-2 pe-5 text-end tabular-nums">
                      <div className="text-default-900 font-medium">{money(w.amount)}</div>
                      {w.adjustments > 0 && (
                        <div className="text-default-500 text-xs" title={adjustments.filter((a) => a.work_order_id === r.work_order_id).map((a) => `${a.label} ${money(a.value)}`).join(', ')}>
                          incl. {money(w.adjustments)} adj.
                        </div>
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={week.days.length + 3} className="text-default-500 py-6 text-center">
                    Nobody is scheduled at {property.name} this week.
                  </td>
                </tr>
              )}
              {looseAdjustments.map((a) => (
                <tr key={`adj-${a.id}`} className="border-default-300 border-t">
                  <td colSpan={week.days.length + 2} className="text-default-500 py-2 ps-5 text-sm">
                    {a.label}
                  </td>
                  <td className="text-default-900 py-2 ps-2 pe-5 text-end font-medium tabular-nums">{money(a.value)}</td>
                </tr>
              ))}
            </tbody>
            {weeks.length > 0 && (
              <tfoot>
                <tr className="border-default-300 bg-light/40 text-default-900 border-t font-semibold tabular-nums">
                  <td className="bg-light/40 text-default-500 sticky left-0 z-[1] py-3 ps-5 text-xs font-medium uppercase">Daily total</td>
                  {week.days.map((d) => {
                    const list = entries.filter((e) => e.date === d && rows.some((r) => r.work_order_id === e.work_order_id))
                    const onShift = new Set(list.map((e) => rows.find((r) => r.work_order_id === e.work_order_id)?.person_id)).size
                    return (
                      <td key={d} className="px-1 py-3 text-center">
                        {list.length ? (
                          <>
                            {hrs(minutesOf(list))}
                            <span className="text-default-500 block text-xs font-normal">{onShift} on shift</span>
                          </>
                        ) : (
                          <span className="text-default-500 font-normal">—</span>
                        )}
                      </td>
                    )
                  })}
                  <td className="px-2 py-3 text-end">{hrs(totals.total)}</td>
                  <td className="py-3 ps-2 pe-5 text-end whitespace-nowrap">{money(totals.amount)}</td>
                </tr>
              </tfoot>
            )}
          </table>
        </div>
      </section>

      {declining && timesheet && <DeclineModal timesheetId={timesheet.id} recruiter={recruiter} onClose={() => setDeclining(false)} />}
    </>
  )
}

/** Sent to you → Approved → Invoiced, where this week sits, in the PM's words. */
const StatusSteps = ({ status, isCurrent }: { status: string; isCurrent: boolean }) => {
  if (status === 'draft' || status === 'voided') {
    return (
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <StatusPill status={status} />
        <span className="text-default-500 text-sm">
          {status === 'voided'
            ? 'This week’s invoice was voided.'
            : isCurrent
              ? 'Updates as people clock in and out. Your recruiter sends it once the week closes.'
              : 'Your recruiter is reviewing this week and will send it to you.'}
        </span>
      </div>
    )
  }

  const steps = [status === 'declined' ? 'Declined' : 'Sent to you', 'Approved', 'Invoiced']
  const done = INVOICED.includes(status)

  return (
    <ol className="flex flex-wrap items-center gap-1.5 text-sm" aria-label="Timesheet status">
      {steps.map((step, i) => {
        const current = !done && i === 0
        const complete = done
        return (
          <li
            key={step}
            aria-current={current ? 'step' : undefined}
            className={cn('flex items-center gap-2 whitespace-nowrap', current ? 'text-default-900 font-semibold' : complete ? 'text-default-900' : 'text-default-500')}>
            {i > 0 && <span className="bg-default-300 h-px w-5" aria-hidden="true" />}
            <span
              aria-hidden="true"
              className={cn(
                'size-2.5 rounded-full border-2',
                complete && 'border-success bg-success',
                current && (status === 'declined' ? 'border-danger bg-danger' : 'border-secondary bg-secondary ring-secondary/20 ring-3'),
                !complete && !current && 'border-default-400',
              )}
            />
            {step}
          </li>
        )
      })}
    </ol>
  )
}

/** Hours first; punch times on the toggle. An open punch reads "in since 8:02 am". */
const DayCell = ({ list, mode }: { list: Entry[]; mode: 'hours' | 'punches' }) => {
  if (!list.length) return <span className="text-default-400">—</span>

  const open = list.find((e) => e.end_time === null)
  const minutes = minutesOf(list)

  if (mode === 'punches') {
    return (
      <span className="text-default-700 block text-xs leading-relaxed whitespace-nowrap">
        {list.map((e) => (
          <span key={e.id} className="block">
            {shortClock(e.start_time)}–{e.end_time ? shortClock(e.end_time) : 'now'}
          </span>
        ))}
      </span>
    )
  }

  return (
    <span className="block leading-tight">
      {minutes > 0 && <span className="text-default-900 font-semibold tabular-nums">{hrs(minutes)}</span>}
      {open && <span className="text-info-hover block text-xs font-medium whitespace-nowrap">in since {formatClockTime(open.start_time)}</span>}
    </span>
  )
}

const DeclineModal = ({ timesheetId, recruiter, onClose }: { timesheetId: number; recruiter: string; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({ reason: '', category: '' })

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/timesheets/${timesheetId}/decline`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" role="dialog" aria-modal="true" aria-labelledby="decline-title" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <h4 id="decline-title" className="card-title">
            Decline this week
          </h4>
        </div>
        <div className="card-body p-5">
          <p className="text-default-500 mb-4">Tell {recruiter} what&apos;s wrong. They&apos;ll fix it and send the week again.</p>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label" htmlFor="decline-category">
                What&apos;s wrong (optional)
              </label>
              <select id="decline-category" className="form-select" value={data.category} onChange={(e) => setData('category', e.target.value)}>
                <option value="">—</option>
                <option value="Wrong Hours">Wrong hours</option>
                <option value="Wrong Rate">Wrong rate</option>
                <option value="Unauthorized Work">Work we didn&apos;t authorize</option>
                <option value="Other">Something else</option>
              </select>
            </div>
            <div>
              <label className="form-label" htmlFor="decline-reason">
                Note
              </label>
              <textarea
                id="decline-reason"
                className="form-textarea"
                rows={3}
                placeholder="e.g. James worked a double on Thursday, but only one shift shows."
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                required
                autoFocus
              />
              {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="btn bg-danger hover:bg-danger-hover px-4 font-semibold text-white" disabled={processing}>
                Decline week
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
