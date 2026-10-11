import SidePanel from '@/components/SidePanel'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'
import { ReactNode } from 'react'

export type WorkOrderPanelData = {
  id: number
  status: string
  status_label: string
  is_temporary_assignment: boolean
  person_id: number
  contractor: string | null
  email: string | null
  phone: string | null
  property_id: number
  property: string | null
  position_id: number
  position: string | null
  start_date: string
  end_date: string | null
  pay_rate: number
  bill_rate: number
  ot_pay_rate: number
  ot_bill_rate: number
  bible_rate: { pay_rate: number; bill_rate: number } | null
  direct_hire: { threshold_hours: number; worked_hours: number; remaining_hours: number; percent: number; eligible: boolean; unrestricted: boolean }
  weeks: { week_start: string; week_end: string; total_minutes: number; overtime_minutes: number }[]
  this_week_start: string
  history: { date: string; text: string; work_order_id: number | null }[]
  can: { edit: boolean; close: boolean; transfer: boolean; temp: boolean }
}

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const hrs = (minutes: number) => (minutes / 60).toFixed(2)
// Noon, so no timezone offset can push the date to a neighbouring day.
const at = (d: string) => new Date(d + 'T12:00:00')
const monthDay = (d: string) => at(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
const longDate = (d: string) => at(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const initials = (name: string | null) =>
  (name ?? '?')
    .split(' ')
    .filter(Boolean)
    .map((p) => p[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

const statusBadge = (s: string) =>
  s === 'active' ? 'bg-success/15 text-success' : s === 'closed' ? 'bg-secondary/15 text-secondary' : 'bg-warning/15 text-warning'

const Section = ({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) => (
  <section className="border-default-200 border-b pb-4 last:border-b-0">
    <div className="mb-2 flex items-baseline justify-between gap-2">
      <h5 className="text-default-500 text-xs font-semibold tracking-wider uppercase">{title}</h5>
      {action}
    </div>
    {children}
  </section>
)

/** The rate this placement pays, said against what the property's Bible pays for the position today. */
const rateNote = (w: WorkOrderPanelData): string | null => {
  if (!w.bible_rate) return null
  const diff = w.pay_rate - w.bible_rate.pay_rate
  if (diff === 0) return `Matches ${w.property}'s current ${w.position} rate.`
  return `${money(Math.abs(diff))} ${diff > 0 ? 'above' : 'below'} ${w.property}'s current ${w.position} pay of ${money(w.bible_rate.pay_rate)}.`
}

/**
 * How long the rest of the direct-hire threshold takes at the recent pace:
 * the two latest finished weeks, so a short first week or the week in
 * progress doesn't skew it.
 */
const paceNote = (w: WorkOrderPanelData): string | null => {
  const dh = w.direct_hire
  if (dh.unrestricted || dh.eligible || w.status !== 'active') return null
  const finished = w.weeks.filter((k) => k.week_start !== w.this_week_start).slice(0, 2)
  if (finished.length === 0) return `${Math.round(dh.remaining_hours).toLocaleString()} h to go.`
  const pace = finished.reduce((sum, k) => sum + k.total_minutes, 0) / 60 / finished.length
  if (pace < 1) return `${Math.round(dh.remaining_hours).toLocaleString()} h to go.`
  return `${Math.round(dh.remaining_hours).toLocaleString()} h to go · about ${Math.round(dh.remaining_hours / pace)} weeks at the recent ${Math.round(pace)} h a week.`
}

type Props = {
  workOrder: WorkOrderPanelData
  onClose: () => void
  onTransfer: () => void
  onTemp: () => void
  onCloseWorkOrder: () => void
  /** Ignore Escape while a dialog opened from the panel is on top. */
  escapeDisabled?: boolean
}

/** One work order without leaving the list: rates, direct-hire progress, recent hours, history and actions. */
const WorkOrderPanel = ({ workOrder: w, onClose, onTransfer, onTemp, onCloseWorkOrder, escapeDisabled }: Props) => {
  const margin = w.bill_rate - w.pay_rate
  const note = rateNote(w)
  const pace = paceNote(w)
  const dh = w.direct_hire

  return (
    <SidePanel
      title={w.contractor}
      subtitle={`${w.position ?? ''} · ${w.property ?? ''}`}
      leading={
        <div className="bg-light text-default-700 flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold">{initials(w.contractor)}</div>
      }
      toolbar={
        <div className="flex flex-wrap items-center gap-2">
          <span className={cn('badge badge-label', statusBadge(w.status))}>{w.status_label}</span>
          {w.is_temporary_assignment && <span className="badge badge-label bg-info/15 text-info">Temporary</span>}
          <span className="text-default-400">
            Work order #{w.id} · {w.end_date && w.status === 'closed' ? `${longDate(w.start_date)} – ${longDate(w.end_date)}` : `Started ${longDate(w.start_date)}`}
          </span>
        </div>
      }
      onClose={onClose}
      escapeDisabled={escapeDisabled}
      footer={
        <>
          <div className="flex flex-wrap gap-2">
            {w.can.edit && (
              <Link href={`/admin/work-orders/${w.id}/edit`} className="btn btn-sm bg-primary hover:bg-primary-hover font-semibold text-white">
                <Icon icon="edit" className="size-4" /> Edit
              </Link>
            )}
            {w.can.transfer && (
              <button type="button" className="btn btn-sm border-default-300 hover:border-default-400 border" onClick={onTransfer}>
                Transfer
              </button>
            )}
            {w.can.temp && (
              <button type="button" className="btn btn-sm border-default-300 hover:border-default-400 border" onClick={onTemp}>
                Temporary
              </button>
            )}
          </div>
          {w.can.close && (
            <button type="button" className="text-danger text-sm font-medium hover:underline" onClick={onCloseWorkOrder}>
              Close work order
            </button>
          )}
        </>
      }>
      <div className="space-y-4">
        <Section title="Rates">
          <dl className="grid grid-cols-2 gap-x-4 gap-y-2">
            {[
              ['Pay', w.pay_rate],
              ['Bill', w.bill_rate],
              ['OT pay', w.ot_pay_rate],
              ['OT bill', w.ot_bill_rate],
            ].map(([label, cents]) => (
              <div key={label}>
                <dt className="text-default-400 text-xs">{label}</dt>
                <dd className="text-default-900 text-base font-semibold tabular-nums">{money(cents as number)}</dd>
              </div>
            ))}
          </dl>
          <p className="mt-2 text-sm">
            Margin <span className="text-default-900 font-semibold">{money(margin)}</span> an hour{w.bill_rate ? ` · ${((margin / w.bill_rate) * 100).toFixed(1)}%` : ''}
          </p>
          {note && <p className={cn('mt-1 text-sm', note.startsWith('Matches') ? 'text-default-400' : 'text-default-700')}>{note}</p>}
        </Section>

        <Section title="Toward direct hire">
          {dh.unrestricted ? (
            <p className="text-default-500 text-sm">No threshold: the property may hire directly at any time.</p>
          ) : (
            <>
              <div className="flex justify-between text-sm">
                <span>
                  <span className="text-default-900 font-semibold">{Math.round(dh.worked_hours).toLocaleString()}</span> of{' '}
                  {Math.round(dh.threshold_hours).toLocaleString()} h
                </span>
                {dh.eligible ? <span className="badge badge-label bg-success/15 text-success">Eligible</span> : <span className="text-default-400">{dh.percent}%</span>}
              </div>
              <div
                className="bg-default-100 mt-1.5 h-1.5 overflow-hidden rounded"
                role="progressbar"
                aria-label="Progress toward direct-hire eligibility"
                aria-valuenow={dh.percent}
                aria-valuemin={0}
                aria-valuemax={100}>
                <div className="bg-primary h-1.5" style={{ width: `${Math.max(2, dh.percent)}%` }} />
              </div>
              {pace && <p className="text-default-400 mt-1.5 text-sm">{pace}</p>}
            </>
          )}
        </Section>

        <Section
          title="Recent weeks"
          action={
            <Link href={`/admin/properties/${w.property_id}/grid`} className="text-primary text-sm hover:underline">
              Open timesheet
            </Link>
          }>
          {w.weeks.length === 0 ? (
            <p className="text-default-400 text-sm">No hours yet.</p>
          ) : (
            <ul className="divide-default-200 divide-y text-sm">
              {w.weeks.map((k) => (
                <li key={k.week_start}>
                  <Link
                    href={`/admin/properties/${w.property_id}/grid?week=${k.week_start}`}
                    className="hover:bg-light/60 -mx-2 flex items-center justify-between gap-2 rounded px-2 py-1.5">
                    <span>
                      {monthDay(k.week_start)} – {monthDay(k.week_end)}
                      {k.week_start === w.this_week_start && <span className="text-default-400 text-xs"> · this week</span>}
                    </span>
                    <span className="flex items-center gap-2">
                      {k.overtime_minutes > 0 && <span className="badge badge-label bg-warning/15 text-warning">{hrs(k.overtime_minutes)} OT</span>}
                      <span className="text-default-900 font-semibold tabular-nums">{hrs(k.total_minutes)} h</span>
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Section>

        <Section title="Contact">
          <div className="flex flex-col gap-1 text-sm">
            {w.phone && (
              <a href={`tel:${w.phone}`} className="hover:text-primary inline-flex items-center gap-2">
                <Icon icon="phone" className="size-4" /> {w.phone}
              </a>
            )}
            {w.email && (
              <a href={`mailto:${w.email}`} className="hover:text-primary inline-flex items-center gap-2">
                <Icon icon="mail" className="size-4" /> {w.email}
              </a>
            )}
            <Link href={`/admin/people/${w.person_id}`} className="text-primary inline-flex items-center gap-2 hover:underline">
              <Icon icon="user" className="size-4" /> Open profile
            </Link>
          </div>
        </Section>

        <Section title="History">
          <ol className="space-y-1.5 text-sm">
            {w.history.map((h, i) => (
              <li key={i} className="flex gap-3">
                <span className="text-default-400 w-24 shrink-0 tabular-nums">{longDate(h.date)}</span>
                <span>
                  {h.text}
                  {h.work_order_id && <> #{h.work_order_id}</>}
                </span>
              </li>
            ))}
          </ol>
        </Section>
      </div>
    </SidePanel>
  )
}

export default WorkOrderPanel
