import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import IdentityCard, { FactRow, PersonAvatar } from '@/components/people/IdentityCard'
import Icon from '@/components/wrappers/Icon'
import { cn, toPascalCase } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, ReactNode, useEffect, useState } from 'react'

// --- Types -------------------------------------------------------------------

type PersonInfo = {
  id: number
  name: string
  email: string
  phone: string | null
  status: string
  status_label: string
  is_active: boolean
  is_staff: boolean
  avatar: string | null
  city_state: string
  hire_date: string | null
  contractor_since: string | null
  terminated_at: string | null
  recruiter: string | null
  roles: string[]
}

type WorkOrderRow = {
  id: number
  property: string | null
  property_id: number
  position: string | null
  status: string
  start_date: string | null
  end_date: string | null
  pay_rate: number | null
  bill_rate: number | null
}

type DirectHire = { threshold_hours: number; worked_hours: number; remaining_hours: number; percent: number; eligible: boolean; unrestricted: boolean }

type Placement = {
  id: number
  property_id: number
  property: string | null
  position: string | null
  start_date: string
  pay_rate: number | null
  bill_rate: number | null
  direct_hire: DirectHire
  weeks_to_go: number | null
}

type WeekRow = {
  id: number
  week_start: string
  week_end: string
  is_current: boolean
  property_id: number
  property: string | null
  position: string | null
  regular_minutes: number
  overtime_minutes: number
  holiday_minutes: number
  training_minutes: number
}

type LastFullWeek = { week_start: string; week_end: string; total_minutes: number; overtime_minutes: number }

type AdjustmentRow = {
  id: number
  item: string | null
  type: string
  type_label: string
  value: number
  is_billable: boolean
  notes: string | null
  created_at: string | null
}

type PtoPayload = {
  year_start: string
  year_end: string
  buckets: { bucket: string; label: string; allotted: number; used: number; available: number }[]
}

type HistoryRow = { id: number; description: string; event: string | null; causer: string | null; created_at: string | null }

type ChargeRow = {
  id: number
  reason: string
  reason_label: string
  total_amount: number
  amount_per_payment: number
  num_payments: number
  applied_count: number
  collected_amount: number
  status: string
  can_edit: boolean
}

type Props = {
  person: PersonInfo
  workOrders: WorkOrderRow[] | null
  placements: Placement[] | null
  recentWeeks: WeekRow[] | null
  lastFullWeek: LastFullWeek | null
  hours: WeekRow[] | null
  adjustments: AdjustmentRow[] | null
  charges: ChargeRow[] | null
  pto: PtoPayload | null
  history: HistoryRow[] | null
  can: { openWorkOrders: boolean; infoChanges: boolean; pto: boolean }
}

// --- Helpers -------------------------------------------------------------------

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const hrs = (minutes: number) => (minutes / 60).toFixed(2)
const totalMinutes = (w: WeekRow) => w.regular_minutes + w.overtime_minutes + w.holiday_minutes + w.training_minutes

// Noon, so no timezone offset can push the date to a neighbouring day.
const at = (iso: string) => new Date(`${iso}T12:00:00`)
/** "Oct 5 – 11", or "Sep 28 – Oct 4" across a month; the year only when it isn't this one. */
const weekLabel = (start: string, end: string) => {
  const s = at(start)
  const e = at(end)
  const month = (d: Date) => d.toLocaleDateString('en-US', { month: 'short' })
  const range = s.getMonth() === e.getMonth() ? `${month(s)} ${s.getDate()} – ${e.getDate()}` : `${month(s)} ${s.getDate()} – ${month(e)} ${e.getDate()}`
  return e.getFullYear() === new Date().getFullYear() ? range : `${range}, ${e.getFullYear()}`
}

const gridHref = (w: WeekRow) => `/admin/properties/${w.property_id}/grid?week=${w.week_start}`

const statusBadge: Record<string, string> = {
  contractor_active: 'bg-success/15 text-success',
  staff_active: 'bg-success/15 text-success',
  contractor_inactive: 'bg-secondary/15 text-secondary',
  staff_inactive: 'bg-secondary/15 text-secondary',
  pending_termination: 'bg-warning/15 text-warning',
  terminated: 'bg-danger/15 text-danger',
}

const woBadge: Record<string, string> = {
  active: 'bg-success/15 text-success',
  suspended: 'bg-warning/15 text-warning',
  closed: 'bg-secondary/15 text-secondary',
}

/** A titled card, as on My Profile: the title, a line on what it holds, an optional action. */
const Section = ({ title, description, action, children, className }: { title: string; description?: ReactNode; action?: ReactNode; children: ReactNode; className?: string }) => (
  <section className={cn('card mb-0', className)}>
    <div className="card-header flex flex-wrap items-start justify-between gap-3">
      <div>
        <h4 className="card-title">{title}</h4>
        {description && <p className="text-default-500 mt-1 text-sm">{description}</p>}
      </div>
      {action}
    </div>
    {children}
  </section>
)

/** One figure at the top of the Overview: a label, the value, a note under it. */
const Stat = ({ label, value, note, noteClass }: { label: string; value: string; note: ReactNode; noteClass?: string }) => (
  <div className="card mb-0 h-full">
    <div className="card-body">
      <div className="text-default-400 text-sm">{label}</div>
      <div className="text-default-900 text-lg font-semibold tabular-nums">{value}</div>
      <div className={cn('text-sm', noteClass ?? 'text-default-500')}>{note}</div>
    </div>
  </div>
)

const Empty = ({ children }: { children: ReactNode }) => <p className="card-body text-default-400 text-sm">{children}</p>

const ProgressBar = ({ percent, label }: { percent: number; label: string }) => (
  <div className="bg-default-100 mt-1.5 h-1.5 overflow-hidden rounded" role="progressbar" aria-label={label} aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100}>
    <div className="bg-primary h-1.5" style={{ width: `${Math.max(2, percent)}%` }} />
  </div>
)

// --- Overview ----------------------------------------------------------------------

const PersonIdentityCard = ({ person, canInfoChanges }: { person: PersonInfo; canInfoChanges: boolean }) => (
  <IdentityCard
    avatar={<PersonAvatar name={person.name} avatar={person.avatar} />}
    name={person.name}
    subtitle={person.is_staff ? (person.roles[0] ?? 'Staff') : 'Contractor'}
    badges={
      <>
        <span className={cn('badge badge-label', statusBadge[person.status] ?? 'bg-light text-default-600')}>{person.status_label}</span>
        {person.is_staff &&
          person.roles.slice(1).map((role) => (
            <span key={role} className="badge badge-label bg-primary/15 text-primary">
              {role}
            </span>
          ))}
      </>
    }
    footer={
      <>
        Phone, address and other personal details are kept by HR. Changes go through{' '}
        {canInfoChanges ? (
          <Link href="/admin/info-changes" className="text-primary hover:underline">
            Info Changes
          </Link>
        ) : (
          'an info change request'
        )}
        .
      </>
    }>
    <FactRow icon="mail" label="Email">
      <a href={`mailto:${person.email}`} className="hover:text-primary">
        {person.email}
      </a>
    </FactRow>
    <FactRow icon="phone" label="Phone">
      {person.phone ? (
        <a href={`tel:${person.phone}`} className="hover:text-primary">
          {person.phone}
        </a>
      ) : (
        'Not set'
      )}
    </FactRow>
    {person.city_state && (
      <FactRow icon="map-pin" label="Location">
        {person.city_state}
      </FactRow>
    )}
    {person.recruiter && (
      <FactRow icon="user-circle" label="Recruiter">
        {person.recruiter}
      </FactRow>
    )}
    {person.hire_date && (
      <FactRow icon="calendar" label="Hired">
        {person.hire_date}
      </FactRow>
    )}
    {person.contractor_since && (
      <FactRow icon="calendar-check" label="Contractor since">
        {person.contractor_since}
      </FactRow>
    )}
    {person.terminated_at && (
      <FactRow icon="calendar-x" label="Terminated">
        {person.terminated_at}
      </FactRow>
    )}
  </IdentityCard>
)

/**
 * How much of the hiring fee the contractor still owes — the question a
 * recruiter asks without wanting to open a tab. The warning only shows while
 * there is something left to collect.
 */
const FeeCard = ({ charge }: { charge: ChargeRow | null }) => {
  if (charge === null) {
    return (
      <Section title="Hiring fee">
        <Empty>No hiring fee on file.</Empty>
      </Section>
    )
  }

  const remaining = Math.max(0, charge.total_amount - charge.collected_amount)
  const percent = charge.total_amount > 0 ? Math.round((charge.collected_amount / charge.total_amount) * 100) : 0
  const collecting = remaining > 0 && charge.status !== 'cancelled'

  return (
    <Section title={charge.reason_label}>
      <div className="card-body">
        {collecting && (
          <div className="bg-warning/15 text-warning mb-5 flex items-center gap-3 rounded px-4 py-3" role="alert">
            <Icon icon="alert-triangle" className="text-lg" />
            <span>
              <span className="block text-lg font-semibold">{money(remaining)} still to collect</span>
              <span className="text-sm">
                {money(charge.amount_per_payment)} a pay period, {charge.applied_count} of {charge.num_payments} taken
              </span>
            </span>
          </div>
        )}
        <div className="mb-1 flex items-baseline justify-between text-sm">
          <span>
            Paid <span className="text-default-900 font-semibold">{money(charge.collected_amount)}</span> of {money(charge.total_amount)}
          </span>
          <span className="text-default-400">{percent}%</span>
        </div>
        <ProgressBar percent={percent} label="Hiring fee collected" />
        {!collecting && (
          <p className="text-default-400 mt-2 text-sm">{charge.status === 'cancelled' && remaining > 0 ? 'Collection stopped.' : 'Paid in full.'}</p>
        )}
      </div>
    </Section>
  )
}

/** "Placed at": the one property, or how many when a contractor works at several. */
const placedAt = (placements: Placement[]): { value: string; note: string } => {
  if (placements.length === 0) return { value: 'Not placed', note: 'No active work orders' }
  const properties = [...new Set(placements.map((p) => p.property ?? '—'))]
  if (placements.length === 1) return { value: properties[0], note: `${placements[0].position ?? ''} since ${placements[0].start_date}` }
  if (properties.length === 1) return { value: properties[0], note: placements.map((p) => p.position).join(' · ') }
  return { value: `${properties.length} properties`, note: properties.join(' · ') }
}

/** The placement furthest along toward direct hire, among those with a threshold. */
const closestToDirectHire = (placements: Placement[]): Placement | null =>
  placements.filter((p) => !p.direct_hire.unrestricted).sort((a, b) => b.direct_hire.percent - a.direct_hire.percent)[0] ?? null

const PlacementRow = ({ p, canOpen }: { p: Placement; canOpen: boolean }) => {
  const dh = p.direct_hire
  return (
    <div className="border-default-200 grid grid-cols-2 items-center gap-x-5 gap-y-3 border-b py-4 last:border-b-0 md:grid-cols-[2fr_1fr_1fr_2fr_auto]">
      <div className="col-span-2 md:col-span-1">
        <Link href={`/admin/properties/${p.property_id}`} className="text-default-900 hover:text-primary font-semibold">
          {p.property}
        </Link>
        <div className="text-default-400 text-sm">
          {p.position} · since {p.start_date}
        </div>
      </div>
      {p.pay_rate !== null && (
        <div>
          <div className="text-default-400 text-xs">Pay</div>
          <div className="text-default-900 font-semibold tabular-nums">{money(p.pay_rate)}</div>
        </div>
      )}
      {p.bill_rate !== null && (
        <div>
          <div className="text-default-400 text-xs">Bill</div>
          <div className="text-default-900 font-semibold tabular-nums">{money(p.bill_rate)}</div>
        </div>
      )}
      <div className="col-span-2 md:col-span-1">
        {dh.unrestricted ? (
          <span className="text-default-400 text-sm">No direct-hire threshold</span>
        ) : dh.eligible ? (
          <span className="badge badge-label bg-success/15 text-success">Eligible for direct hire</span>
        ) : (
          <>
            <div className="flex justify-between text-sm">
              <span>
                <span className="text-default-900 font-semibold">{Math.round(dh.worked_hours).toLocaleString()}</span> of{' '}
                {Math.round(dh.threshold_hours).toLocaleString()} h
              </span>
              <span className="text-default-400">{dh.percent}%</span>
            </div>
            <ProgressBar percent={dh.percent} label={`Progress toward direct hire at ${p.property}`} />
          </>
        )}
      </div>
      {canOpen && (
        <Link href={`/admin/work-orders?panel=${p.id}`} className="btn btn-sm border-default-300 hover:border-default-400 justify-self-start border md:justify-self-end">
          Open #{p.id}
        </Link>
      )}
    </div>
  )
}

const WeekLine = ({ w }: { w: WeekRow }) => (
  <li>
    <Link href={gridHref(w)} className="hover:bg-light/60 -mx-2 flex items-center justify-between gap-3 rounded px-2 py-2">
      <span>
        {weekLabel(w.week_start, w.week_end)} <span className="text-default-400">· {w.property}</span>
        {w.is_current && <span className="badge badge-label bg-light text-default-600 ms-2">This week</span>}
      </span>
      <span className="flex items-center gap-2">
        {w.overtime_minutes > 0 && <span className="badge badge-label bg-warning/15 text-warning">{hrs(w.overtime_minutes)} OT</span>}
        <span className="text-default-900 font-semibold tabular-nums">{hrs(totalMinutes(w))} h</span>
      </span>
    </Link>
  </li>
)

const ContractorOverview = ({
  placements,
  recentWeeks,
  lastFullWeek,
  canOpen,
  onAllHours,
}: {
  placements: Placement[]
  recentWeeks: WeekRow[] | null
  lastFullWeek: LastFullWeek | null
  canOpen: boolean
  onAllHours: (() => void) | null
}) => {
  const placed = placedAt(placements)
  const closest = closestToDirectHire(placements)
  const many = placements.length > 1

  return (
    <div className="flex flex-col gap-6">
      <div className="gap-base grid md:grid-cols-3">
        <Stat label="Placed at" value={placed.value} note={placed.note} />
        {recentWeeks !== null && (
          <Stat
            label="Last full week"
            value={lastFullWeek ? `${hrs(lastFullWeek.total_minutes)} h` : 'No hours yet'}
            note={
              lastFullWeek
                ? lastFullWeek.overtime_minutes > 0
                  ? `${hrs(lastFullWeek.overtime_minutes)} h overtime`
                  : weekLabel(lastFullWeek.week_start, lastFullWeek.week_end)
                : 'Nothing finished yet'
            }
            noteClass={lastFullWeek && lastFullWeek.overtime_minutes > 0 ? 'text-warning' : undefined}
          />
        )}
        {closest && (
          <Stat
            label={many ? 'Closest to direct hire' : 'Toward direct hire'}
            value={
              closest.direct_hire.eligible
                ? 'Eligible'
                : `${Math.round(closest.direct_hire.worked_hours).toLocaleString()} of ${Math.round(closest.direct_hire.threshold_hours).toLocaleString()} h`
            }
            note={[
              many ? closest.property : null,
              closest.weeks_to_go !== null ? `about ${closest.weeks_to_go} weeks at the recent pace` : closest.direct_hire.eligible ? null : `${closest.direct_hire.percent}%`,
            ]
              .filter(Boolean)
              .join(' · ')
              .replace(/^a/, 'A')}
          />
        )}
      </div>

      {placements.length > 0 && (
        <Section
          title={many ? 'Current placements' : 'Current placement'}
          description={many ? 'Every active work order. Each has its own rates and its own count toward direct hire.' : 'The work order they clock in on today.'}>
          <div className="px-6 py-2">
            {placements.map((p) => (
              <PlacementRow key={p.id} p={p} canOpen={canOpen} />
            ))}
          </div>
        </Section>
      )}

      {recentWeeks !== null && (
        <Section
          title="Recent weeks"
          description="Hours on every placement. Open a week in the property's timesheet."
          action={
            onAllHours && (
              <button type="button" className="btn btn-sm border-default-300 hover:border-default-400 border" onClick={onAllHours}>
                All hours
              </button>
            )
          }>
          {recentWeeks.length === 0 ? (
            <Empty>No hours recorded yet.</Empty>
          ) : (
            <ul className="divide-default-200 divide-y px-6 py-4">
              {recentWeeks.map((w) => (
                <WeekLine key={w.id} w={w} />
              ))}
            </ul>
          )}
        </Section>
      )}
    </div>
  )
}

const StaffOverview = ({ pto, canPto }: { pto: PtoPayload | null; canPto: boolean }) =>
  canPto ? (
    <Section
      title="Time off"
      description={
        pto ? (
          <>
            Anniversary year {pto.year_start} – {pto.year_end}
          </>
        ) : undefined
      }>
      {pto ? (
        <div className="card-body">
          <div className="gap-base grid md:grid-cols-3">
            {pto.buckets.map((b) => (
              <div key={b.bucket} className="border-default-200 rounded-lg border p-4">
                <p className="text-default-400 text-sm">{b.label}</p>
                <p className="text-dark mt-1 text-2xl font-semibold tabular-nums">
                  {b.available}
                  <span className="text-default-400 text-sm font-normal"> / {b.allotted} h</span>
                </p>
                <p className="text-default-400 text-xs">{b.used} used or pending</p>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <Empty>No open time-off year yet.</Empty>
      )}
    </Section>
  ) : null

// --- Other tabs ----------------------------------------------------------------------

const WorkOrdersTab = ({ workOrders, canOpen }: { workOrders: WorkOrderRow[]; canOpen: boolean }) => {
  const withRates = workOrders.some((wo) => wo.pay_rate !== null)

  return (
    <Section title="Work orders" description="Every placement, newest first. Open one for its rates, hours and actions.">
      {workOrders.length === 0 ? (
        <Empty>No work orders yet.</Empty>
      ) : (
        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Property</th>
                <th>Position</th>
                <th>Started</th>
                <th>Ended</th>
                {withRates && <th className="text-end">Pay</th>}
                {withRates && <th className="text-end">Bill</th>}
                <th>Status</th>
                {canOpen && <th className="text-center">Actions</th>}
              </tr>
            </thead>
            <tbody>
              {workOrders.map((wo) => (
                <tr key={wo.id}>
                  <td className="font-medium">
                    <Link href={`/admin/properties/${wo.property_id}`} className="hover:text-primary">
                      {wo.property ?? '—'}
                    </Link>
                  </td>
                  <td>{wo.position ?? '—'}</td>
                  <td className="whitespace-nowrap">{wo.start_date ?? '—'}</td>
                  <td className="whitespace-nowrap">{wo.end_date ?? '—'}</td>
                  {withRates && <td className="text-end tabular-nums">{wo.pay_rate !== null ? money(wo.pay_rate) : '—'}</td>}
                  {withRates && <td className="text-end tabular-nums">{wo.bill_rate !== null ? money(wo.bill_rate) : '—'}</td>}
                  <td>
                    <span className={cn('badge badge-label capitalize', woBadge[wo.status] ?? 'bg-light text-default-600')}>{wo.status}</span>
                  </td>
                  {canOpen && (
                    <td className="text-center">
                      <Link href={`/admin/work-orders?panel=${wo.id}`} className="btn btn-sm border-default-300 hover:border-default-400 border">
                        Open
                      </Link>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Section>
  )
}

const HoursTab = ({ hours }: { hours: WeekRow[] }) => {
  const withTraining = hours.some((w) => w.training_minutes > 0)
  const cell = (minutes: number) => (minutes > 0 ? hrs(minutes) : '—')

  return (
    <Section title="Hours" description={`The last ${hours.length || 12} weekly summaries, newest first. Open a week in the property's timesheet.`}>
      {hours.length === 0 ? (
        <Empty>No hours recorded yet.</Empty>
      ) : (
        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Week</th>
                <th>Property</th>
                <th>Position</th>
                <th className="text-end">Regular</th>
                <th className="text-end">Overtime</th>
                <th className="text-end">Holiday</th>
                {withTraining && <th className="text-end">Training</th>}
                <th className="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              {hours.map((w) => (
                <tr key={w.id}>
                  <td className="whitespace-nowrap">
                    <Link href={gridHref(w)} className="hover:text-primary font-medium">
                      {weekLabel(w.week_start, w.week_end)}
                    </Link>
                    {w.is_current && <span className="badge badge-label bg-light text-default-600 ms-2">This week</span>}
                  </td>
                  <td>{w.property ?? '—'}</td>
                  <td>{w.position ?? '—'}</td>
                  <td className="text-end tabular-nums">{hrs(w.regular_minutes)}</td>
                  <td className="text-end tabular-nums">{cell(w.overtime_minutes)}</td>
                  <td className="text-end tabular-nums">{cell(w.holiday_minutes)}</td>
                  {withTraining && <td className="text-end tabular-nums">{cell(w.training_minutes)}</td>}
                  <td className="text-default-900 text-end font-semibold tabular-nums">{hrs(totalMinutes(w))}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Section>
  )
}

/**
 * Deductions spread across pay periods. The total is fixed when the charge is
 * created; what is editable here is the pace, or stopping collection.
 */
const ChargesCard = ({ charges }: { charges: ChargeRow[] }) => {
  const [editing, setEditing] = useState<ChargeRow | null>(null)

  const cancel = (row: ChargeRow) => {
    confirmAction({
      title: 'Stop collecting',
      message: (
        <>
          Stop collecting the <strong>{row.reason_label.toLowerCase()}</strong>? Payments already taken out of pay are unchanged; nothing further is deducted.
        </>
      ),
      confirmLabel: 'Stop collecting',
      onConfirm: () => router.delete(`/admin/contractor-charges/${row.id}`, { preserveScroll: true }),
    })
  }

  return (
    <Section title="Deductions" description="Hiring fees and uniform charges, taken out of pay over several pay periods.">
      {charges.length === 0 ? (
        <Empty>No deductions scheduled.</Empty>
      ) : (
        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Charge</th>
                <th className="text-end">Total</th>
                <th className="text-end">Per period</th>
                <th className="text-end">Collected</th>
                <th>Status</th>
                <th className="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              {charges.map((row) => (
                <tr key={row.id}>
                  <td className="font-medium">{row.reason_label}</td>
                  <td className="text-end tabular-nums">{money(row.total_amount)}</td>
                  <td className="text-end tabular-nums">{money(row.amount_per_payment)}</td>
                  <td className="text-end tabular-nums">
                    {money(row.collected_amount)}
                    <span className="text-default-400 block text-xs">
                      {row.applied_count} of {row.num_payments} payments
                    </span>
                  </td>
                  <td>
                    <span className={cn('badge badge-label', row.status === 'active' ? 'bg-warning/15 text-warning' : 'bg-light text-default-600')}>
                      {toPascalCase(row.status)}
                    </span>
                  </td>
                  <td>
                    <div className="flex justify-center gap-1.5">
                      {row.can_edit && (
                        <>
                          <button
                            className="btn btn-icon border-default-300 hover:border-default-400 border"
                            title="Change per-period amount"
                            aria-label={`Change the per-period amount of the ${row.reason_label.toLowerCase()}`}
                            onClick={() => setEditing(row)}>
                            <Icon icon="pencil" className="text-base" />
                          </button>
                          <button
                            className="btn btn-icon border-default-300 hover:border-danger text-danger border"
                            title="Stop collecting"
                            aria-label={`Stop collecting the ${row.reason_label.toLowerCase()}`}
                            onClick={() => cancel(row)}>
                            <Icon icon="x" className="text-base" />
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {editing && <EditChargeModal row={editing} onClose={() => setEditing(null)} />}
    </Section>
  )
}

const EditChargeModal = ({ row, onClose }: { row: ChargeRow; onClose: () => void }) => {
  const { data, setData, processing, errors } = useForm({
    amount_per_payment: String(row.amount_per_payment / 100),
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    router.patch(
      `/admin/contractor-charges/${row.id}`,
      { amount_per_payment: Math.round(Number(data.amount_per_payment) * 100) },
      { preserveScroll: true, onSuccess: onClose },
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <h4 className="card-title">{row.reason_label}</h4>
        </div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label htmlFor="amount_per_payment" className="form-label">
                Amount per pay period
              </label>
              <input
                id="amount_per_payment"
                type="number"
                step="0.01"
                min="0.01"
                className="form-input"
                value={data.amount_per_payment}
                onChange={(e) => setData('amount_per_payment', e.target.value)}
                required
              />
              {errors.amount_per_payment && <p className="text-danger mt-1 text-sm">{errors.amount_per_payment}</p>}
              <p className="text-default-400 mt-2 text-sm">
                {money(row.total_amount)} total, {money(row.collected_amount)} already collected. Payments already taken are not changed.
              </p>
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>
                Cancel
              </button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>
                Save
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

/** The server sends at most this many adjustments. */
const ADJUSTMENTS_SHOWN = 25

const AdjustmentsCard = ({ adjustments }: { adjustments: AdjustmentRow[] }) => (
  <Section
    title="Adjustments"
    description={`One-off incentives and deductions on their time.${adjustments.length >= ADJUSTMENTS_SHOWN ? ` Showing the latest ${ADJUSTMENTS_SHOWN}.` : ''}`}>
    {adjustments.length === 0 ? (
      <Empty>No adjustments yet.</Empty>
    ) : (
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-xs uppercase">
              <th>Date</th>
              <th>Item</th>
              <th>Type</th>
              <th className="text-end">Amount</th>
              <th>Billable</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            {adjustments.map((a) => (
              <tr key={a.id}>
                <td className="whitespace-nowrap">{a.created_at ?? '—'}</td>
                <td className="font-medium">{a.item ?? '—'}</td>
                <td>
                  <span className={cn('badge badge-label', a.type === 'incentive' ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger')}>{a.type_label}</span>
                </td>
                <td className={cn('text-end font-medium tabular-nums', a.type === 'deduction' && 'text-danger')}>
                  {a.type === 'deduction' ? `-${money(a.value)}` : money(a.value)}
                </td>
                <td>{a.is_billable ? 'Yes' : 'No'}</td>
                <td className="text-default-400">{a.notes ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )}
  </Section>
)

const HistoryTab = ({ history, name }: { history: HistoryRow[]; name: string }) => (
  <Section title="History" description={`Changes to ${name}'s record, newest first.`}>
    {history.length === 0 ? (
      <Empty>No changes recorded yet.</Empty>
    ) : (
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-xs uppercase">
              <th>When</th>
              <th>What</th>
              <th>By</th>
            </tr>
          </thead>
          <tbody>
            {history.map((h) => (
              <tr key={h.id}>
                <td className="text-nowrap">{h.created_at}</td>
                <td>{h.description}</td>
                <td>{h.causer ?? 'System'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )}
  </Section>
)

// --- Page -------------------------------------------------------------------------

/** Old tab links still land somewhere sensible. */
const HASH_ALIASES: Record<string, string> = { charges: 'pay', adjustments: 'pay', pto: 'overview' }

const Page = ({ person, workOrders, placements, recentWeeks, lastFullWeek, hours, adjustments, charges, pto, history, can }: Props) => {
  const firstName = person.name.split(' ')[0]
  // A cancelled or fully-paid fee still shows, so "no fee card" means "no fee".
  const hiringFee = charges?.find((c) => c.reason === 'hiring_fee') ?? null

  // Overview always exists, so there is always a tab to land on.
  const tabs = [
    { key: 'overview', label: 'Overview', show: true },
    { key: 'work-orders', label: 'Work orders', show: workOrders !== null },
    { key: 'hours', label: 'Hours', show: hours !== null },
    { key: 'pay', label: 'Pay & deductions', show: charges !== null || adjustments !== null },
    { key: 'history', label: 'History', show: history !== null },
  ].filter((t) => t.show)

  // Active tab lives in the URL hash so tabs are deep-linkable and survive refresh.
  const tabFromHash = () => {
    const raw = typeof window === 'undefined' ? '' : window.location.hash.slice(1)
    const hash = HASH_ALIASES[raw] ?? raw
    return tabs.some((t) => t.key === hash) ? hash : 'overview'
  }
  const [active, setActive] = useState(tabFromHash)

  useEffect(() => {
    const onHashChange = () => setActive(tabFromHash())
    window.addEventListener('hashchange', onHashChange)
    return () => window.removeEventListener('hashchange', onHashChange)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const selectTab = (key: string) => {
    setActive(key)
    window.history.replaceState(null, '', `#${key}`)
  }

  const activeWorkOrders = placements?.length ?? 0

  return (
    <>
      <Head title={person.name} />
      <PageBreadcrumb title={person.name} subtitle="People" />

      {/* Tabs sit under the page title, full width, as on My Profile and the property page. */}
      <nav className="border-default-300 mt-4 mb-5 flex flex-wrap gap-1 border-b" aria-label="Profile sections" role="tablist">
        {tabs.map((t) => (
          <button
            key={t.key}
            type="button"
            role="tab"
            aria-selected={active === t.key}
            onClick={() => selectTab(t.key)}
            className={cn(
              '-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 font-medium focus:outline-hidden',
              active === t.key ? 'border-primary text-default-900' : 'text-default-500 hover:text-default-900 border-transparent',
            )}>
            {t.label}
            {t.key === 'work-orders' && activeWorkOrders > 1 && <span className="badge badge-label bg-light text-default-600">{activeWorkOrders} active</span>}
          </button>
        ))}
      </nav>

      {active === 'overview' && (
        <div className="gap-base grid items-start xl:grid-cols-3">
          <div className="flex flex-col gap-6">
            <PersonIdentityCard person={person} canInfoChanges={can.infoChanges} />
            {!person.is_staff && charges !== null && <FeeCard charge={hiringFee} />}
          </div>
          <div className="xl:col-span-2">
            {person.is_staff ? (
              <StaffOverview pto={pto} canPto={can.pto} />
            ) : (
              <ContractorOverview
                placements={placements ?? []}
                recentWeeks={recentWeeks}
                lastFullWeek={lastFullWeek}
                canOpen={can.openWorkOrders}
                onAllHours={hours !== null ? () => selectTab('hours') : null}
              />
            )}
          </div>
        </div>
      )}

      {active === 'work-orders' && workOrders !== null && <WorkOrdersTab workOrders={workOrders} canOpen={can.openWorkOrders} />}
      {active === 'hours' && hours !== null && <HoursTab hours={hours} />}
      {active === 'pay' && (
        <div className="flex flex-col gap-6">
          {charges !== null && <ChargesCard charges={charges} />}
          {adjustments !== null && <AdjustmentsCard adjustments={adjustments} />}
        </div>
      )}
      {active === 'history' && history !== null && <HistoryTab history={history} name={firstName} />}
    </>
  )
}

export default Page
