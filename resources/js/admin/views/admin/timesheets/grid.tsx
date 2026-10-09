import PageBreadcrumb from '@/components/PageBreadcrumb'
import ConfirmModal from '@/components/ConfirmModal'
import SidePanel from '@/components/SidePanel'
import Icon from '@/components/wrappers/Icon'
import { cn, formatClockTime } from '@/utils/helpers'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { FormEvent, ReactNode, useEffect, useState } from 'react'

type Row = {
  work_order_id: number
  person_id: number
  contractor: string | null
  position: string | null
  /** A closed or suspended work order is listed only when it has time in the week. */
  status: string
  status_label: string
  end_date: string | null
  pay_rate: number | null
  bill_rate: number | null
}
type Entry = {
  id: number
  work_order_id: number
  date: string | null
  start_time: string | null
  end_time: string | null
  duration_minutes: number | null
  entry_type: string
  gps_flags: string[]
}
type Summary = { regular_minutes: number; overtime_minutes: number; training_minutes: number; total_pay: number; total_bill: number }

type Timesheet = { id: number; status: string; status_label: string; decline_reason: string | null }
type Adjustment = {
  id: number
  person: string
  person_id: number
  work_order_id: number | null
  type: string
  value: number
  is_billable: boolean
  notes: string | null
  source_type: string
}
type Can = { edit: boolean; correct: boolean; remove: boolean; submit: boolean; adjust: boolean }

type Props = {
  property: { id: number; name: string; timezone: string }
  week: { start: string; end: string; days: string[] }
  period: { id: number; status: string } | null
  timesheet: Timesheet | null
  rows: Row[]
  entries: Entry[]
  summaries: Record<number, Summary>
  adjustments: Adjustment[]
  can: Can
}

/** A day past this many minutes is coloured — usually a missed clock-out or a double shift. */
const LONG_DAY_MINUTES = 600

const hrs = (min: number | null | undefined) => ((min ?? 0) / 60).toFixed(2)
const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
// Noon, so no timezone offset can push the date to a neighbouring day.
const at = (d: string) => new Date(d + 'T12:00:00')
const dayLabel = (d: string) => at(d).toLocaleDateString('en-US', { weekday: 'short', month: 'numeric', day: 'numeric' })
const longDay = (d: string) => at(d).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
const weekday = (d: string) => at(d).toLocaleDateString('en-US', { weekday: 'short' })
const monthDay = (d: string) => at(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
const isWeekend = (d: string) => [0, 6].includes(at(d).getDay())
const addDays = (d: string, n: number) => {
  const x = new Date(d + 'T00:00:00Z')
  x.setUTCDate(x.getUTCDate() + n)
  return x.toISOString().slice(0, 10)
}
/** "8:06a" — the compact form for grid cells; panels use formatClockTime. */
const shortClock = (t: string | null) => {
  if (!t) return '—'
  const [h, m] = t.split(':').map(Number)
  return `${h % 12 || 12}:${String(m).padStart(2, '0')}${h < 12 ? 'a' : 'p'}`
}
const initials = (name: string | null) =>
  (name ?? '?')
    .split(' ')
    .filter(Boolean)
    .map((p) => p[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

/** Today's date at the property, so "still on the clock" is judged in its timezone. */
const todayAt = (tz: string) => new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(new Date())
const timezoneName = (tz: string) =>
  new Intl.DateTimeFormat('en-US', { timeZone: tz, timeZoneName: 'long' }).formatToParts(new Date()).find((p) => p.type === 'timeZoneName')?.value ?? tz

const minutesOf = (list: Entry[]) => list.reduce((sum, e) => sum + (e.duration_minutes ?? 0), 0)
const isOpen = (e: Entry) => e.end_time === null
/** An open punch from an earlier day: the contractor never clocked out. Today's is just a shift in progress. */
const isMissingOut = (e: Entry, today: string) => isOpen(e) && e.date !== null && e.date < today
const needsLook = (e: Entry, today: string) => e.gps_flags.length > 0 || isMissingOut(e, today)

const adjustmentsFor = (row: Row, adjustments: Adjustment[]) =>
  adjustments.filter((a) => a.work_order_id === row.work_order_id || (a.work_order_id === null && a.person_id === row.person_id))
const signed = (a: Adjustment) => (a.type === 'deduction' ? -a.value : a.value)

type ConfirmState = {
  title: string
  message: React.ReactNode
  confirmLabel?: string
  tone?: 'danger' | 'primary'
  onConfirm: () => void
}

type PanelState = { kind: 'day'; workOrderId: number; date: string; adding: boolean } | { kind: 'person'; workOrderId: number }

// Every mutation here keeps the page state, so an open panel survives the reload.
const keep = { preserveScroll: true, preserveState: true }

const Page = ({ property, week, period, timesheet, rows, entries, summaries, adjustments, can }: Props) => {
  const [mode, setMode] = useState<'hours' | 'punches'>('hours')
  const [panel, setPanel] = useState<PanelState | null>(null)
  const [confirming, setConfirming] = useState<ConfirmState | null>(null)

  const today = todayAt(property.timezone)
  const isThisWeek = week.days.includes(today)
  const issues = entries.filter((e) => needsLook(e, today)).length
  const gpsFlagged = entries.filter((e) => e.gps_flags.length > 0).length
  // The server refuses to send a week with any punch still open (no hours yet).
  const openPunches = entries.filter(isOpen)
  const submitError = usePage<{ errors: Record<string, string> }>().props.errors?.timesheet

  const submitForApproval = () => {
    if (!timesheet) return

    setConfirming({
      title: 'Send for approval',
      message: (
        <>
          Send the week of <strong className="text-default-900">{monthDay(week.start)} – {monthDay(week.end)}</strong> to the property manager for
          approval?
          {gpsFlagged > 0 && (
            <span className="text-warning mt-2 block">
              {gpsFlagged} {gpsFlagged === 1 ? 'punch was' : 'punches were'} recorded without verified GPS.
            </span>
          )}
          <span className="text-default-400 mt-2 block">
            The week is locked while it is with them — further punches need the timesheet to be declined first.
          </span>
        </>
      ),
      confirmLabel: 'Send',
      tone: 'primary',
      onConfirm: () => router.post(`/admin/timesheets/${timesheet.id}/submit`, {}, { preserveScroll: true }),
    })
  }

  // Live updates (Reverb): refresh the grid when anyone changes this property's
  // entries for the week currently in view.
  useEffect(() => {
    if (typeof window === 'undefined' || !window.Echo) return
    const channel = window.Echo.private(`property.${property.id}`)
    channel.listen('.time-entry.saved', (e: { week_start: string }) => {
      if (e.week_start === week.start) {
        router.reload({ only: ['entries', 'summaries'] })
      }
    })
    return () => {
      window.Echo.leave(`property.${property.id}`)
    }
  }, [property.id, week.start])

  const goToWeek = (start?: string) =>
    router.get(`/admin/properties/${property.id}/grid`, start ? { week: start } : {}, { preserveScroll: true })

  const cellEntries = (wo: number, date: string) =>
    entries.filter((e) => e.work_order_id === wo && e.date === date).sort((a, b) => (a.start_time ?? '').localeCompare(b.start_time ?? ''))

  // The prompt names exactly what is going — a generic "Are you sure?" does not
  // help someone who mis-clicked and cannot tell which punch they hit.
  const confirmEntryRemoval = (entry: Entry, contractor: string | null, date: string) =>
    setConfirming({
      title: 'Remove punch',
      message: (
        <>
          Remove the{' '}
          <strong className="text-default-900">
            {formatClockTime(entry.start_time)} – {entry.end_time ? formatClockTime(entry.end_time) : 'open'}
          </strong>{' '}
          punch for <strong className="text-default-900">{contractor ?? 'this contractor'}</strong> on{' '}
          <strong className="text-default-900">{dayLabel(date)}</strong>?
          <span className="text-default-400 mt-2 block">This cannot be undone, and the week&apos;s hours will be recalculated.</span>
        </>
      ),
      onConfirm: () => router.delete(`/admin/time-entries/${entry.id}`, keep),
    })

  const confirmAdjustmentRemoval = (adjustment: Adjustment) =>
    setConfirming({
      title: 'Remove adjustment',
      message: (
        <>
          Remove the{' '}
          <strong className="text-default-900">
            {money(adjustment.value)} {adjustment.type}
          </strong>{' '}
          for <strong className="text-default-900">{adjustment.person}</strong>?
          <span className="text-default-400 mt-2 block">
            This cannot be undone{adjustment.is_billable ? ', and it will no longer be billed to the property' : ''}.
          </span>
        </>
      ),
      onConfirm: () => router.delete(`/admin/adjustments/${adjustment.id}`, keep),
    })

  const weekOf = (r: Row) => {
    const s = summaries[r.work_order_id]
    return {
      regular: s?.regular_minutes ?? 0,
      overtime: s?.overtime_minutes ?? 0,
      training: s?.training_minutes ?? 0,
      total: (s?.regular_minutes ?? 0) + (s?.overtime_minutes ?? 0) + (s?.training_minutes ?? 0),
      pay: s?.total_pay ?? 0,
      bill: s?.total_bill ?? 0,
    }
  }
  const totals = rows.map(weekOf).reduce(
    (t, w) => ({ regular: t.regular + w.regular, overtime: t.overtime + w.overtime, total: t.total + w.total, pay: t.pay + w.pay, bill: t.bill + w.bill }),
    { regular: 0, overtime: 0, total: 0, pay: 0, bill: 0 },
  )
  // People, not rows: a pay increase can give one contractor two work orders.
  const personOf = new Map(rows.map((r) => [r.work_order_id, r.person_id]))
  const people = new Set(rows.map((r) => r.person_id)).size
  const overtimePeople = new Set(rows.filter((r) => weekOf(r).overtime > 0).map((r) => r.person_id)).size
  const shownWorkOrders = new Set(rows.map((r) => r.work_order_id))
  const dayEntries = (d: string) => entries.filter((e) => e.date === d && shownWorkOrders.has(e.work_order_id))

  const panelRow = panel ? rows.find((r) => r.work_order_id === panel.workOrderId) : undefined
  const closePanel = () => setPanel(null)

  return (
    <>
      <Head title={`Timesheet — ${property.name}`} />
      <PageBreadcrumb title={property.name} subtitle="Weekly Timesheet" />

      <div className="card mb-4">
        <div className="card-body flex flex-wrap items-center justify-between gap-4 p-4 lg:px-5">
          <div className="flex flex-wrap items-center gap-1.5">
            <button className="btn btn-icon hover:bg-light" onClick={() => goToWeek(addDays(week.start, -7))} aria-label="Previous week" title="Previous week">
              <Icon icon="chevron-left" className="size-5" />
            </button>
            <div className="px-1.5">
              <div className="text-default-900 text-base font-semibold whitespace-nowrap">
                {monthDay(week.start)} – {monthDay(week.end)}, {week.end.slice(0, 4)}
              </div>
              <div className="text-default-400 text-xs">Times in {timezoneName(property.timezone)}</div>
            </div>
            <button className="btn btn-icon hover:bg-light" onClick={() => goToWeek(addDays(week.start, 7))} aria-label="Next week" title="Next week">
              <Icon icon="chevron-right" className="size-5" />
            </button>
            {!isThisWeek && (
              <button className="btn btn-sm btn-light ms-1" onClick={() => goToWeek()}>
                This week
              </button>
            )}
          </div>

          {timesheet && <StatusTrack status={timesheet.status} label={timesheet.status_label} />}

          <div className="flex items-center gap-2">
            <Link href={`/admin/properties/${property.id}`} className="btn btn-light text-nowrap">
              <Icon icon="building" className="me-1 size-4" /> Property
            </Link>
            {can.submit && (
              <button
                className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white disabled:opacity-50"
                onClick={submitForApproval}
                disabled={openPunches.length > 0}
                title={openPunches.length > 0 ? 'Every punch needs a clock-out first' : undefined}>
                Send for Approval
              </button>
            )}
          </div>
        </div>
      </div>

      {submitError && <div className="bg-danger/10 text-danger mb-4 rounded-lg px-4 py-3 text-sm">{submitError}</div>}

      {can.submit && openPunches.length > 0 && (
        <div className="bg-warning/15 text-default-900 mb-4 rounded-lg px-4 py-3 text-sm">
          <strong className="text-warning">
            {openPunches.length === 1 ? '1 punch has' : `${openPunches.length} punches have`} no clock-out, so this week can&apos;t be sent yet.
          </strong>{' '}
          Add the clock-out or remove the punch:
          <span className="mt-1.5 flex flex-wrap gap-2">
            {openPunches.map((e) => {
              const row = rows.find((r) => r.work_order_id === e.work_order_id)
              return (
                <button
                  key={e.id}
                  className="bg-card hover:text-primary rounded-md px-2 py-0.5 text-xs font-medium shadow-sm"
                  disabled={!row || !e.date}
                  onClick={() => row && e.date && setPanel({ kind: 'day', workOrderId: row.work_order_id, date: e.date, adding: false })}>
                  {row?.contractor ?? 'Contractor'} · {e.date ? dayLabel(e.date) : '—'} · in {formatClockTime(e.start_time)}
                  {e.date === today && ' (still on the clock)'}
                </button>
              )
            })}
          </span>
        </div>
      )}

      {timesheet?.status === 'declined' && timesheet.decline_reason && (
        <div className="bg-danger/10 text-danger mb-4 rounded-lg px-4 py-3 text-sm">
          <strong>Declined:</strong> {timesheet.decline_reason}
        </div>
      )}

      {!period ? (
        <div className="card">
          <div className="card-body text-default-400 p-6">No payroll period for this week yet.</div>
        </div>
      ) : (
        <>
          <div className="card mb-4 grid grid-cols-2 lg:grid-cols-5">
            <Stat label="Hours" value={hrs(totals.total)} note={`${people} ${people === 1 ? 'person' : 'people'} · ${hrs(totals.regular)} regular`} />
            <Stat
              label="Overtime"
              value={hrs(totals.overtime)}
              note={overtimePeople ? <span className="text-secondary">{overtimePeople} over 40 h</span> : 'Nobody over 40 h'}
            />
            <Stat label="Payroll" value={money(totals.pay)} note="Before taxes" />
            <Stat
              label="Billable"
              value={money(totals.bill)}
              note={`Margin ${money(totals.bill - totals.pay)}${totals.bill ? ` · ${(((totals.bill - totals.pay) / totals.bill) * 100).toFixed(1)}%` : ''}`}
            />
            <Stat
              label="Needs a look"
              value={issues ? String(issues) : 'All clear'}
              valueClass={issues ? 'text-warning' : 'text-success'}
              note={issues ? 'No clock-out, or GPS not verified' : 'No missing or flagged punches'}
              className="col-span-2 lg:col-span-1"
            />
          </div>

          <div className="card">
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
              <div className="text-default-400 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                <span className="inline-flex items-center gap-1.5">
                  <span className="bg-secondary size-2 rounded-sm" /> Over 10 h in a day
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <span className="bg-warning size-2 rounded-full" /> Needs a look
                </span>
                <span>Click a day for its punches, or a name for the week</span>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full min-w-[1080px] table-fixed text-sm">
                <colgroup>
                  <col className="w-60" />
                  {week.days.map((d) => (
                    <col key={d} />
                  ))}
                  <col className="w-28" />
                  <col className="w-28" />
                </colgroup>
                <thead>
                  <tr className="border-default-300 text-default-400 border-b text-xs uppercase">
                    <th className="bg-card sticky left-0 z-[1] py-2.5 ps-5 text-start font-medium">Contractor</th>
                    {week.days.map((d) => (
                      <th key={d} className={cn('px-1 py-2.5 text-center font-medium', isWeekend(d) && 'bg-light/30', d === today && 'text-primary')}>
                        {weekday(d)}
                        <span className={cn('block text-[13px] font-semibold normal-case', d === today ? 'text-primary' : 'text-default-900')}>{monthDay(d)}</span>
                      </th>
                    ))}
                    <th className="px-2 py-2.5 text-end font-medium">Week</th>
                    <th className="py-2.5 pe-5 ps-2 text-end font-medium">Pay · Bill</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length ? (
                    rows.map((r) => {
                      const w = weekOf(r)
                      const adj = adjustmentsFor(r, adjustments)
                      const net = adj.reduce((s, a) => s + signed(a), 0)
                      return (
                        <tr key={r.work_order_id} className="border-default-300 group/row hover:bg-primary/[0.03] border-b last:border-0">
                          <td className="bg-card sticky left-0 z-[1] py-1 ps-5">
                            <button
                              className="flex w-full items-center gap-2.5 py-1.5 text-start"
                              onClick={() => setPanel({ kind: 'person', workOrderId: r.work_order_id })}
                              aria-label={`Open ${r.contractor ?? 'contractor'}'s week`}>
                              <Avatar name={r.contractor} />
                              <span className="min-w-0">
                                <span className="text-default-900 hover:text-primary block truncate font-medium">{r.contractor}</span>
                                <span className="text-default-400 flex items-center gap-1.5 text-xs">
                                  <span className="truncate">{r.position}</span>
                                  {/* Only in the week it actually stopped — it was running in the weeks before. */}
                                  {r.status !== 'active' && (!r.end_date || r.end_date <= week.end) && (
                                    <Chip tone="muted">{r.end_date ? `Ended ${monthDay(r.end_date)}` : r.status_label}</Chip>
                                  )}
                                </span>
                              </span>
                            </button>
                          </td>
                          {week.days.map((d) => (
                            <td key={d} className={cn('px-0.5 py-1', isWeekend(d) && 'bg-light/30')}>
                              <DayCell
                                row={r}
                                date={d}
                                list={cellEntries(r.work_order_id, d)}
                                mode={mode}
                                today={today}
                                canAdd={can.edit}
                                onOpen={(adding) => setPanel({ kind: 'day', workOrderId: r.work_order_id, date: d, adding })}
                              />
                            </td>
                          ))}
                          <td className="px-2 py-1 text-end">
                            <div className="text-default-900 text-[15px] font-semibold tabular-nums">{hrs(w.total)}</div>
                            <div className="mt-0.5 flex flex-wrap justify-end gap-1">
                              {w.overtime > 0 && <Chip tone="secondary">+{hrs(w.overtime)} OT</Chip>}
                              {w.training > 0 && <Chip tone="info">{hrs(w.training)} trn</Chip>}
                              {adj.length > 0 && (
                                <Chip tone={net < 0 ? 'danger' : 'success'} title={adj.map((a) => `${a.type} ${money(a.value)}`).join(', ')}>
                                  {net < 0 ? '−' : '+'}
                                  {money(Math.abs(net))}
                                </Chip>
                              )}
                            </div>
                          </td>
                          <td className="py-1 pe-5 ps-2 text-end whitespace-nowrap tabular-nums">
                            <div className="text-default-900 font-medium">{money(w.pay)}</div>
                            <div className="text-default-400 text-xs">{money(w.bill)}</div>
                          </td>
                        </tr>
                      )
                    })
                  ) : (
                    <tr>
                      <td colSpan={week.days.length + 3} className="text-default-400 py-4 text-center">
                        No active work orders at this property.
                      </td>
                    </tr>
                  )}
                </tbody>
                {rows.length > 0 && (
                  <tfoot>
                    <tr className="border-default-300 bg-light/40 text-default-900 border-t font-semibold tabular-nums">
                      <td className="bg-light/40 text-default-400 sticky left-0 z-[1] py-3 ps-5 text-xs font-medium uppercase">Daily total</td>
                      {week.days.map((d) => {
                        const list = dayEntries(d)
                        const onShift = new Set(list.map((e) => personOf.get(e.work_order_id))).size
                        return (
                          <td key={d} className="px-1 py-3 text-center">
                            {list.length ? (
                              <>
                                {hrs(minutesOf(list))}
                                <span className="text-default-400 block text-xs font-normal">{onShift} on shift</span>
                              </>
                            ) : (
                              <span className="text-default-400 font-normal">—</span>
                            )}
                          </td>
                        )
                      })}
                      <td className="px-2 py-3 text-end">{hrs(totals.total)}</td>
                      <td className="py-3 pe-5 ps-2 text-end whitespace-nowrap">
                        {money(totals.pay)}
                        <span className="text-default-400 block text-xs font-normal">{money(totals.bill)}</span>
                      </td>
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
          </div>
        </>
      )}

      {panel?.kind === 'day' && panelRow && (
        <DayPanel
          key={`${panel.workOrderId}-${panel.date}`}
          row={panelRow}
          date={panel.date}
          days={week.days}
          list={cellEntries(panel.workOrderId, panel.date)}
          today={today}
          can={can}
          startAdding={panel.adding}
          onNavigate={(date) => setPanel({ kind: 'day', workOrderId: panel.workOrderId, date, adding: false })}
          onOpenWeek={() => setPanel({ kind: 'person', workOrderId: panel.workOrderId })}
          onRemove={(e) => confirmEntryRemoval(e, panelRow.contractor, panel.date)}
          onClose={closePanel}
          escapeDisabled={confirming !== null}
        />
      )}

      {panel?.kind === 'person' && panelRow && period && (
        <PersonPanel
          key={panel.workOrderId}
          row={panelRow}
          week={week}
          periodId={period.id}
          summary={weekOf(panelRow)}
          minutesByDay={week.days.map((d) => minutesOf(cellEntries(panel.workOrderId, d)))}
          adjustments={adjustmentsFor(panelRow, adjustments)}
          canAdjust={can.adjust}
          onOpenDay={(date) => setPanel({ kind: 'day', workOrderId: panel.workOrderId, date, adding: false })}
          onRemoveAdjustment={confirmAdjustmentRemoval}
          onClose={closePanel}
          escapeDisabled={confirming !== null}
        />
      )}

      {confirming && (
        <ConfirmModal
          title={confirming.title}
          message={confirming.message}
          confirmLabel={confirming.confirmLabel ?? 'Remove'}
          tone={confirming.tone ?? 'danger'}
          onConfirm={confirming.onConfirm}
          onClose={() => setConfirming(null)}
        />
      )}
    </>
  )
}

/** Draft → Pending approval → Approved → Invoiced, with where this week sits. */
const StatusTrack = ({ status, label }: { status: string; label: string }) => {
  if (status === 'voided') return <span className="badge badge-label bg-light text-default-600">{label}</span>

  const steps = ['Draft', 'Pending approval', 'Approved', 'Invoiced']
  const at = { draft: 0, declined: 0, pending_approval: 1, approved: 2, invoiced: 3, invoice_sent: 3 }[status] ?? 0

  return (
    <ol className="flex flex-wrap items-center gap-x-1 gap-y-1 text-xs" aria-label="Timesheet status">
      {steps.map((step, i) => (
        <li key={step} className={cn('flex items-center gap-1.5 whitespace-nowrap', i < at && 'text-success', i === at && 'text-default-900 font-semibold', i > at && 'text-default-400')}>
          {i > 0 && <span className="bg-default-300 me-0.5 h-px w-4" />}
          <span
            className={cn(
              'size-2 rounded-full border-[1.5px] border-current',
              i < at && 'bg-current',
              i === at && (status === 'declined' ? 'border-danger bg-danger' : 'border-secondary bg-secondary ring-secondary/20 ring-3'),
            )}
          />
          {i === at ? label : step}
        </li>
      ))}
    </ol>
  )
}

const Stat = ({ label, value, note, valueClass, className }: { label: string; value: string; note: ReactNode; valueClass?: string; className?: string }) => (
  <div className={cn('border-default-300 min-w-0 border-b px-5 py-3.5 last:border-b-0 lg:border-b-0 lg:not-first:border-s [&:nth-child(even)]:border-s', className)}>
    <div className="text-default-400 text-[11px] font-medium tracking-wider uppercase">{label}</div>
    <div className={cn('text-default-900 text-xl font-semibold tabular-nums', valueClass)}>{value}</div>
    <div className="text-default-400 text-xs">{note}</div>
  </div>
)

const Chip = ({ tone, title, children }: { tone: 'secondary' | 'info' | 'success' | 'danger' | 'warning' | 'muted'; title?: string; children: ReactNode }) => {
  const tones = {
    secondary: 'bg-secondary/15 text-secondary',
    info: 'bg-info/15 text-info',
    success: 'bg-success/15 text-success',
    danger: 'bg-danger/15 text-danger',
    warning: 'bg-warning/15 text-warning',
    muted: 'bg-light text-default-500',
  }
  return (
    <span title={title} className={cn('inline-flex h-[18px] items-center rounded-md px-1.5 text-[10.5px] font-semibold whitespace-nowrap tabular-nums', tones[tone])}>
      {children}
    </span>
  )
}

const Avatar = ({ name }: { name: string | null }) => (
  <span className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold">{initials(name)}</span>
)

/** One contractor-day: the day's hours up front; the punches themselves open in the side panel. */
const DayCell = ({
  row,
  date,
  list,
  mode,
  today,
  canAdd,
  onOpen,
}: {
  row: Row
  date: string
  list: Entry[]
  mode: 'hours' | 'punches'
  today: string
  canAdd: boolean
  onOpen: (adding: boolean) => void
}) => {
  const who = `${row.contractor ?? 'Contractor'}, ${longDay(date)}`

  if (!list.length) {
    return canAdd ? (
      <button
        className="group hover:bg-primary/10 focus-visible:bg-primary/10 flex min-h-14 w-full items-center justify-center rounded-lg"
        onClick={() => onOpen(true)}
        aria-label={`${who}: no punches. Add one`}>
        <span className="text-default-300 group-hover:hidden group-focus-visible:hidden">—</span>
        <span className="text-primary hidden text-xs font-medium group-hover:block group-focus-visible:block">+ Add</span>
      </button>
    ) : (
      <div className="text-default-300 flex min-h-14 items-center justify-center">—</div>
    )
  }

  const minutes = minutesOf(list)
  const open = list.find(isOpen)
  const missingOut = list.some((e) => isMissingOut(e, today))
  const flagged = list.some((e) => e.gps_flags.length > 0)
  const training = list.some((e) => e.entry_type === 'training')
  const last = list[list.length - 1]

  let body: ReactNode
  if (mode === 'punches') {
    body = (
      <>
        {list.map((e) => (
          <span key={e.id} className="text-default-600 text-[11.5px] leading-snug whitespace-nowrap tabular-nums">
            {shortClock(e.start_time)}–{e.end_time ? shortClock(e.end_time) : <span className="text-warning font-semibold">?</span>}
          </span>
        ))}
        <span className="text-default-400 text-[11px] tabular-nums">{hrs(minutes)} h</span>
      </>
    )
  } else {
    const main = open && minutes === 0 ? (missingOut ? 'No clock-out' : 'On the clock') : hrs(minutes)
    const sub = open
      ? `${missingOut ? 'In' : 'In since'} ${shortClock(open.start_time)}${missingOut ? ', no out' : ''}`
      : `${shortClock(list[0].start_time)} – ${shortClock(last.end_time)}`
    body = (
      <>
        <span
          className={cn(
            'font-semibold tabular-nums',
            open && minutes === 0 ? 'text-[12.5px]' : 'text-[15px]',
            missingOut ? 'text-warning' : open && minutes === 0 ? 'text-info' : minutes > LONG_DAY_MINUTES ? 'text-secondary' : 'text-default-900',
          )}>
          {main}
        </span>
        <span className="text-default-400 text-[11.5px] whitespace-nowrap tabular-nums">{sub}</span>
      </>
    )
  }

  return (
    <button
      className={cn('hover:bg-primary/10 relative flex min-h-14 w-full flex-col items-center justify-center gap-px rounded-lg px-1 py-1.5', missingOut && 'bg-warning/15')}
      onClick={() => onOpen(false)}
      aria-label={`${who}: ${missingOut ? 'missing clock-out' : `${hrs(minutes)} hours`}${flagged ? ', GPS not verified' : ''}`}>
      {flagged && <span className="bg-warning ring-card absolute end-2 top-1.5 size-1.5 rounded-full ring-2" title="Punch without verified GPS" />}
      {body}
      {training && <Chip tone="info">Training</Chip>}
    </button>
  )
}

/** A contractor's punches for one day: read, correct, remove, add. */
const DayPanel = ({
  row,
  date,
  days,
  list,
  today,
  can,
  startAdding,
  onNavigate,
  onOpenWeek,
  onRemove,
  onClose,
  escapeDisabled,
}: {
  row: Row
  date: string
  days: string[]
  list: Entry[]
  today: string
  can: Can
  startAdding: boolean
  onNavigate: (date: string) => void
  onOpenWeek: () => void
  onRemove: (entry: Entry) => void
  onClose: () => void
  escapeDisabled: boolean
}) => {
  const [adding, setAdding] = useState(startAdding && can.edit)
  const [editing, setEditing] = useState<number | null>(null)
  const i = days.indexOf(date)
  const prev = days[i - 1]
  const next = days[i + 1]
  const minutes = minutesOf(list)

  return (
    <SidePanel
      title={row.contractor}
      subtitle={`${row.position ?? ''} · ${longDay(date)}`}
      leading={<Avatar name={row.contractor} />}
      onClose={onClose}
      escapeDisabled={escapeDisabled}
      toolbar={
        <div className="flex items-center justify-between">
          <button className={cn('text-primary hover:underline', !prev && 'invisible')} onClick={() => prev && onNavigate(prev)}>
            ‹ {prev && weekday(prev)}
          </button>
          <span className="text-default-900 font-semibold tabular-nums">
            {hrs(minutes)} h{list.length > 0 && ` · ${list.length} ${list.length === 1 ? 'punch' : 'punches'}`}
          </span>
          <button className={cn('text-primary hover:underline', !next && 'invisible')} onClick={() => next && onNavigate(next)}>
            {next && weekday(next)} ›
          </button>
        </div>
      }
      footer={
        <>
          <button className="text-primary text-sm hover:underline" onClick={onOpenWeek}>
            See {row.contractor?.split(' ')[0] ?? 'their'}&apos;s week
          </button>
          {can.edit && !adding && editing === null && (
            <button className="btn btn-sm bg-primary hover:bg-primary-hover font-semibold text-white" onClick={() => setAdding(true)}>
              + Add punch
            </button>
          )}
        </>
      }>
      {list.map((e, idx) => {
        const before = list[idx - 1]
        const gap = before?.end_time && e.start_time ? toMinutes(e.start_time) - toMinutes(before.end_time) : 0
        return (
          <div key={e.id} className="space-y-3">
            {gap > 0 && (
              <div className="text-default-400 flex items-center gap-3 text-xs">
                <span className="bg-default-300 h-px flex-1" /> Break {gap} min <span className="bg-default-300 h-px flex-1" />
              </div>
            )}
            {editing === e.id ? (
              <EntryForm entry={e} onDone={() => setEditing(null)} />
            ) : (
              <div className="border-default-300 rounded-xl border px-4 py-3">
                <div className="flex items-baseline justify-between gap-3">
                  <span className="text-default-900 text-[15px] font-semibold tabular-nums">
                    {formatClockTime(e.start_time)} → {e.end_time ? formatClockTime(e.end_time) : '…'}
                  </span>
                  <span className="text-default-900 font-medium tabular-nums">{isOpen(e) ? '—' : `${hrs(e.duration_minutes)} h`}</span>
                </div>
                <div className="text-default-400 mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                  {e.entry_type === 'training' ? <Chip tone="info">Training</Chip> : <span>Work</span>}
                  {isMissingOut(e, today) && <Chip tone="warning">No clock-out</Chip>}
                  {isOpen(e) && !isMissingOut(e, today) && <Chip tone="info">On the clock</Chip>}
                  {e.gps_flags.map((f) => (
                    <Chip key={f} tone="warning">
                      GPS not verified ({f})
                    </Chip>
                  ))}
                </div>
                {(can.correct || can.remove) && (
                  <div className="mt-2 flex justify-end gap-1">
                    {can.correct && (
                      <button className="btn btn-sm text-primary hover:bg-primary/10" onClick={() => setEditing(e.id)}>
                        {isOpen(e) ? 'Add clock-out' : 'Edit times'}
                      </button>
                    )}
                    {can.remove && (
                      <button className="btn btn-sm text-danger hover:bg-danger/10" onClick={() => onRemove(e)}>
                        Remove
                      </button>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        )
      })}

      {!list.length && !adding && <p className="text-default-400 py-2 text-sm">No punches on {longDay(date)}.</p>}

      {adding && <EntryForm workOrderId={row.work_order_id} date={date} onDone={() => setAdding(false)} />}
    </SidePanel>
  )
}

const toMinutes = (t: string) => {
  const [h, m] = t.split(':').map(Number)
  return h * 60 + m
}

/**
 * Adds a punch (workOrderId + date) or corrects one (entry). The date is fixed
 * either way — moving a punch to another day could land it in a different
 * payroll period, which is a remove-and-re-add rather than an edit.
 */
const EntryForm = ({ entry, workOrderId, date, onDone }: { entry?: Entry; workOrderId?: number; date?: string; onDone: () => void }) => {
  const { data, setData, post, patch, processing, errors } = useForm({
    date: entry?.date ?? date ?? '',
    start_time: entry?.start_time ?? '09:00',
    end_time: entry ? (entry.end_time ?? '') : '17:00',
    entry_type: entry?.entry_type ?? 'work',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (entry) {
      patch(`/admin/time-entries/${entry.id}`, { ...keep, onSuccess: onDone })
    } else {
      post(`/admin/work-orders/${workOrderId}/time-entries`, { ...keep, onSuccess: onDone })
    }
  }

  return (
    <form onSubmit={submit} className="bg-light/60 grid grid-cols-2 gap-3 rounded-xl p-4">
      <div>
        <label className="form-label" htmlFor="entry-start">
          Clock in
        </label>
        <input id="entry-start" type="time" className="form-input" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} autoFocus required />
        {errors.start_time && <p className="text-danger mt-1 text-sm">{errors.start_time}</p>}
      </div>
      <div>
        <label className="form-label" htmlFor="entry-end">
          Clock out
        </label>
        <input id="entry-end" type="time" className="form-input" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} required />
        {errors.end_time && <p className="text-danger mt-1 text-sm">{errors.end_time}</p>}
      </div>
      <div className="col-span-2">
        <label className="form-label" htmlFor="entry-type">
          Type
        </label>
        <select id="entry-type" className="form-select" value={data.entry_type} onChange={(e) => setData('entry_type', e.target.value)}>
          <option value="work">Work</option>
          <option value="training">Training</option>
        </select>
        {errors.date && <p className="text-danger mt-1 text-sm">{errors.date}</p>}
      </div>
      <p className="text-default-400 col-span-2 text-xs">A clock-out earlier than the clock-in counts as an overnight shift.</p>
      <div className="col-span-2 flex justify-end gap-2">
        <button type="button" className="btn btn-sm bg-card hover:text-primary" onClick={onDone}>
          Cancel
        </button>
        <button type="submit" className="btn btn-sm bg-primary hover:bg-primary-hover font-semibold text-white" disabled={processing}>
          {entry ? 'Save' : 'Add punch'}
        </button>
      </div>
    </form>
  )
}

/** A contractor's whole week: hours by day, pay and bill, incentives and deductions. */
const PersonPanel = ({
  row,
  week,
  periodId,
  summary,
  minutesByDay,
  adjustments,
  canAdjust,
  onOpenDay,
  onRemoveAdjustment,
  onClose,
  escapeDisabled,
}: {
  row: Row
  week: Props['week']
  periodId: number
  summary: { regular: number; overtime: number; training: number; total: number; pay: number; bill: number }
  minutesByDay: number[]
  adjustments: Adjustment[]
  canAdjust: boolean
  onOpenDay: (date: string) => void
  onRemoveAdjustment: (a: Adjustment) => void
  onClose: () => void
  escapeDisabled: boolean
}) => {
  const [adding, setAdding] = useState(false)
  const tallest = Math.max(LONG_DAY_MINUTES, ...minutesByDay)

  return (
    <SidePanel
      title={row.contractor}
      subtitle={`${row.position ?? ''} · ${monthDay(week.start)} – ${monthDay(week.end)}`}
      leading={<Avatar name={row.contractor} />}
      onClose={onClose}
      escapeDisabled={escapeDisabled}
      toolbar={
        <div className="flex items-center justify-between">
          <span className="text-default-400">Work order #{row.work_order_id}</span>
          <span className="text-default-900 font-semibold tabular-nums">{hrs(summary.total)} h this week</span>
        </div>
      }
      footer={
        canAdjust && !adding ? (
          <>
            <span />
            <button className="btn btn-sm bg-primary hover:bg-primary-hover font-semibold text-white" onClick={() => setAdding(true)}>
              + Add adjustment
            </button>
          </>
        ) : undefined
      }>
      <SectionTitle>Hours by day</SectionTitle>
      <div className="grid h-24 grid-cols-7 items-end gap-1.5">
        {week.days.map((d, i) => {
          const m = minutesByDay[i]
          return (
            <button key={d} className="group flex h-full flex-col items-center justify-end gap-1 text-[11px]" onClick={() => onOpenDay(d)} title={`Open ${longDay(d)}`}>
              <span className="text-default-900 font-semibold tabular-nums">{m ? hrs(m) : '—'}</span>
              <span
                className={cn('w-full max-w-8 rounded-t-md rounded-b-sm', m ? 'bg-primary/80 group-hover:bg-primary' : 'bg-default-300')}
                style={{ height: `${Math.max(2, (m / tallest) * 56)}px` }}
              />
              <span className="text-default-400">{weekday(d)}</span>
            </button>
          )
        })}
      </div>

      <SectionTitle>Week</SectionTitle>
      <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1.5 text-sm tabular-nums">
        <dt className="text-default-400">Regular</dt>
        <dd className="text-default-900 text-end font-medium">{hrs(summary.regular)} h</dd>
        <dt className="text-default-400">Overtime</dt>
        <dd className={cn('text-end font-medium', summary.overtime ? 'text-secondary' : 'text-default-900')}>{hrs(summary.overtime)} h</dd>
        {summary.training > 0 && (
          <>
            <dt className="text-default-400">Training</dt>
            <dd className="text-default-900 text-end font-medium">{hrs(summary.training)} h</dd>
          </>
        )}
        {row.pay_rate !== null && row.bill_rate !== null && (
          <>
            <dt className="text-default-400">Pay rate · bill rate</dt>
            <dd className="text-default-900 text-end font-medium">
              {money(row.pay_rate)} · {money(row.bill_rate)}
            </dd>
          </>
        )}
        <dt className="text-default-400">Pay</dt>
        <dd className="text-default-900 text-end font-medium">{money(summary.pay)}</dd>
        <dt className="text-default-400">Bill</dt>
        <dd className="text-default-900 text-end font-medium">{money(summary.bill)}</dd>
      </dl>

      <SectionTitle>Adjustments</SectionTitle>
      {adjustments.map((a) => (
        <div key={a.id} className="border-default-300 flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-sm">
          <Chip tone={a.type === 'deduction' ? 'danger' : 'success'}>{a.type === 'deduction' ? 'Deduction' : 'Incentive'}</Chip>
          <span className="min-w-0 flex-1">
            {a.notes}
            {a.is_billable && <span className="text-default-400"> · billed</span>}
            {a.source_type !== 'manual' && <span className="text-default-400"> · {a.source_type.replaceAll('_', ' ')}</span>}
          </span>
          <span className="text-default-900 font-semibold tabular-nums">
            {a.type === 'deduction' ? '−' : '+'}
            {money(a.value)}
          </span>
          {canAdjust && a.source_type === 'manual' && (
            <button
              className="text-danger hover:bg-danger/10 rounded px-1.5 leading-none"
              title={`Remove ${money(a.value)} ${a.type}`}
              aria-label={`Remove ${money(a.value)} ${a.type}`}
              onClick={() => onRemoveAdjustment(a)}>
              ×
            </button>
          )}
        </div>
      ))}
      {!adjustments.length && !adding && <p className="text-default-400 text-sm">No incentives or deductions this week.</p>}
      {adding && <AdjustmentForm periodId={periodId} row={row} onDone={() => setAdding(false)} />}
    </SidePanel>
  )
}

const SectionTitle = ({ children }: { children: ReactNode }) => (
  <div className="text-default-400 pt-1 text-[11px] font-medium tracking-wider uppercase">{children}</div>
)

const AdjustmentForm = ({ periodId, row, onDone }: { periodId: number; row: Row; onDone: () => void }) => {
  const { data, setData, post, transform, processing, errors } = useForm({
    person_id: row.person_id,
    work_order_id: row.work_order_id,
    amount: '',
    type: 'deduction',
    is_billable: false,
    notes: '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    // Only an incentive can be billed to the property.
    transform((d) => ({ ...d, is_billable: d.type === 'incentive' ? d.is_billable : false }))
    post(`/admin/payroll-periods/${periodId}/adjustments`, { ...keep, onSuccess: onDone })
  }

  return (
    <form onSubmit={submit} className="bg-light/60 grid grid-cols-2 gap-3 rounded-xl p-4">
      <div>
        <label className="form-label" htmlFor="adjustment-type">
          Type
        </label>
        <select id="adjustment-type" className="form-select" value={data.type} onChange={(e) => setData('type', e.target.value)}>
          <option value="deduction">Deduction</option>
          <option value="incentive">Incentive</option>
        </select>
      </div>
      <div>
        <label className="form-label" htmlFor="adjustment-amount">
          Amount ($)
        </label>
        <input
          id="adjustment-amount"
          type="number"
          step="0.01"
          min="0.01"
          className="form-input"
          value={data.amount}
          onChange={(e) => setData('amount', e.target.value)}
          autoFocus
          required
        />
        {errors.amount && <p className="text-danger mt-1 text-sm">{errors.amount}</p>}
      </div>
      <div className="col-span-2">
        <label className="form-label" htmlFor="adjustment-notes">
          Note
        </label>
        <input id="adjustment-notes" type="text" className="form-input" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
      </div>
      {data.type === 'incentive' && (
        <label className="col-span-2 flex items-center gap-2 text-sm">
          <input type="checkbox" className="form-checkbox" checked={data.is_billable} onChange={(e) => setData('is_billable', e.target.checked)} />
          Billable (adds to the property invoice)
        </label>
      )}
      <div className="col-span-2 flex justify-end gap-2">
        <button type="button" className="btn btn-sm bg-card hover:text-primary" onClick={onDone}>
          Cancel
        </button>
        <button type="submit" className="btn btn-sm bg-primary hover:bg-primary-hover font-semibold text-white" disabled={processing}>
          Add adjustment
        </button>
      </div>
    </form>
  )
}

export default Page
