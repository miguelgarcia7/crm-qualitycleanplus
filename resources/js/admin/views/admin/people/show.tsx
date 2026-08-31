import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn, toPascalCase } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useEffect, useState } from 'react'

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

type HoursRow = {
  id: number
  week_start: string
  week_end: string
  property: string | null
  position: string | null
  regular_minutes: number
  overtime_minutes: number
  other_minutes: number
}

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
  hours: HoursRow[] | null
  adjustments: AdjustmentRow[] | null
  charges: ChargeRow[] | null
  pto: PtoPayload | null
  history: HistoryRow[] | null
}

// --- Helpers -------------------------------------------------------------------

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const hoursLabel = (minutes: number) => (minutes / 60).toFixed(1)

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
  pending: 'bg-warning/15 text-warning',
  ended: 'bg-secondary/15 text-secondary',
  cancelled: 'bg-danger/15 text-danger',
}

const initialsOf = (name: string) =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

const FactRow = ({ icon, label, children }: { icon: string; label: string; children: React.ReactNode }) => (
  <div className="flex items-center gap-3">
    <div>
      <div className="btn btn-icon bg-light size-8!">
        <Icon icon={icon} className="text-secondary text-lg" />
      </div>
    </div>
    <p className="text-sm">
      {label} <span className="text-dark font-semibold">{children}</span>
    </p>
  </div>
)

// --- Identity card --------------------------------------------------------------

const IdentityCard = ({ person }: { person: PersonInfo }) => (
  <div className="card">
    <div className="card-body">
      <div className="mb-7.5 flex items-center">
        <div className="gap-base flex items-center">
          {person.avatar ? (
            <img src={person.avatar} alt={person.name} className="size-18 shrink-0 rounded-full object-cover" />
          ) : (
            <div className="bg-primary/10 text-primary flex size-18 shrink-0 items-center justify-center rounded-full text-xl font-semibold">
              {initialsOf(person.name)}
            </div>
          )}
          <div>
            <h5 className="font-medium">{person.name}</h5>
            <p className="text-default-400 mb-3">{person.is_staff ? (person.roles[0] ?? 'Staff') : 'Contractor'}</p>
            <div className="flex flex-wrap gap-1.5">
              <span className={cn('badge badge-label', statusBadge[person.status] ?? 'bg-light text-default-600')}>
                {person.status_label}
              </span>
              {person.is_staff &&
                person.roles.slice(1).map((role) => (
                  <span key={role} className="badge badge-label bg-primary/15 text-primary">
                    {role}
                  </span>
                ))}
            </div>
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-y-3">
        <FactRow icon="mail" label="Email">
          {person.email}
        </FactRow>
        <FactRow icon="phone" label="Phone">
          {person.phone ?? 'Not set'}
        </FactRow>
        {person.city_state && (
          <FactRow icon="map-pin" label="Location">
            {person.city_state}
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
        {person.recruiter && (
          <FactRow icon="user-circle" label="Recruiter">
            {person.recruiter}
          </FactRow>
        )}
      </div>
    </div>
  </div>
)

/**
 * How much of a one-off fee the contractor still owes — the question a
 * recruiter asks without wanting to open a tab, so it sits beside the identity
 * card rather than inside Deductions.
 *
 * Alert and progress markup follow the reference library (ui/alerts,
 * ui/progress) rather than being hand-rolled, so the bar carries its ARIA role.
 */
const FeeCard = ({ charge }: { charge: ChargeRow }) => {
  const remaining = Math.max(0, charge.total_amount - charge.collected_amount)
  const percent = charge.total_amount > 0 ? Math.round((charge.collected_amount / charge.total_amount) * 100) : 0

  return (
    <div className="card">
      <div className="card-header">
        <h4 className="card-title">{charge.reason_label}</h4>
      </div>

      <div className="card-body">
        <div className="bg-warning/15 text-warning flex items-center gap-3 rounded px-4 py-3" role="alert">
          <Icon icon="alert-triangle" className="text-lg" />
          <span>
            <span className="block text-lg font-semibold">{money(charge.total_amount)}</span>
            <span className="text-sm">{charge.reason === 'hiring_fee' ? 'New hiring processing fee' : 'Issued uniform charge'}</span>
          </span>
        </div>

        <div className="mt-5">
          <div className="mb-2 flex items-baseline justify-between">
            <span className="font-semibold">Total amount paid</span>
            <span className="font-semibold">{money(charge.collected_amount)}</span>
          </div>

          <div
            className="bg-default-100 flex h-4 w-full overflow-hidden rounded"
            role="progressbar"
            aria-valuenow={percent}
            aria-valuemin={0}
            aria-valuemax={100}>
            <div
              className="bg-warning flex flex-col justify-center overflow-hidden text-center whitespace-nowrap transition duration-500"
              style={{ width: `${percent}%` }}
            />
          </div>

          <p className="text-default-400 mt-2 text-sm">
            {remaining > 0 ? `${money(remaining)} remaining` : 'Paid in full'}
            {charge.status === 'cancelled' && ' — collection cancelled'}
          </p>
        </div>
      </div>
    </div>
  )
}

// --- Tabs ------------------------------------------------------------------------

const WorkOrdersTab = ({ workOrders }: { workOrders: WorkOrderRow[] }) => {
  const withRates = workOrders.some((wo) => wo.pay_rate !== null)

  return (
    <div className="table-wrapper">
      <table className="table table-hover">
        <thead className="thead-sm">
          <tr className="bg-light/25 text-xs uppercase">
            <th>Property</th>
            <th>Position</th>
            <th>Start</th>
            <th>End</th>
            {withRates && <th className="text-end">Pay</th>}
            {withRates && <th className="text-end">Bill</th>}
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {workOrders.length ? (
            workOrders.map((wo) => (
              <tr key={wo.id}>
                <td className="font-medium">
                  <Link href={`/admin/properties/${wo.property_id}`} className="hover:text-primary">
                    {wo.property ?? '—'}
                  </Link>
                </td>
                <td>{wo.position ?? '—'}</td>
                <td>{wo.start_date ?? '—'}</td>
                <td>{wo.end_date ?? '—'}</td>
                {withRates && <td className="text-end">{wo.pay_rate !== null ? money(wo.pay_rate) : '—'}</td>}
                {withRates && <td className="text-end">{wo.bill_rate !== null ? money(wo.bill_rate) : '—'}</td>}
                <td>
                  <span className={cn('badge badge-label capitalize', woBadge[wo.status] ?? 'bg-light text-default-600')}>
                    {wo.status}
                  </span>
                </td>
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={withRates ? 7 : 5} className="text-default-400 py-4 text-center">
                No work orders yet.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

const HoursTab = ({ hours }: { hours: HoursRow[] }) => (
  <div className="table-wrapper">
    <table className="table table-hover">
      <thead className="thead-sm">
        <tr className="bg-light/25 text-xs uppercase">
          <th>Week</th>
          <th>Property</th>
          <th>Position</th>
          <th className="text-end">Regular</th>
          <th className="text-end">Overtime</th>
          <th className="text-end">Other</th>
          <th className="text-end">Total</th>
        </tr>
      </thead>
      <tbody>
        {hours.length ? (
          hours.map((row) => (
            <tr key={row.id}>
              <td className="font-medium">
                {row.week_start} – {row.week_end}
              </td>
              <td>{row.property ?? '—'}</td>
              <td>{row.position ?? '—'}</td>
              <td className="text-end">{hoursLabel(row.regular_minutes)}</td>
              <td className="text-end">{row.overtime_minutes > 0 ? hoursLabel(row.overtime_minutes) : '—'}</td>
              <td className="text-end">{row.other_minutes > 0 ? hoursLabel(row.other_minutes) : '—'}</td>
              <td className="text-end font-semibold">
                {hoursLabel(row.regular_minutes + row.overtime_minutes + row.other_minutes)}
              </td>
            </tr>
          ))
        ) : (
          <tr>
            <td colSpan={7} className="text-default-400 py-4 text-center">
              No hours recorded yet.
            </td>
          </tr>
        )}
      </tbody>
    </table>
    {hours.length > 0 && <p className="text-default-400 mt-2 text-xs">Last {hours.length} weekly summaries, in hours.</p>}
  </div>
)

/**
 * Deductions spread across pay periods. The total is fixed when the charge is
 * created; what is editable here is the pace, or stopping collection.
 */
const ChargesTab = ({ charges }: { charges: ChargeRow[] }) => {
  const [editing, setEditing] = useState<ChargeRow | null>(null)

  const cancel = (row: ChargeRow) => {
    if (!confirm(`Stop collecting the ${row.reason_label.toLowerCase()}? Payments already taken are unchanged.`)) return
    router.delete(`/admin/contractor-charges/${row.id}`, { preserveScroll: true })
  }

  return (
    <>
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
            {charges.length ? (
              charges.map((row) => (
                <tr key={row.id}>
                  <td className="font-medium">{row.reason_label}</td>
                  <td className="text-end">{money(row.total_amount)}</td>
                  <td className="text-end">{money(row.amount_per_payment)}</td>
                  <td className="text-end">
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
                          <button className="btn btn-icon border-default-300 hover:border-default-400 border" title="Change per-period amount" onClick={() => setEditing(row)}>
                            <Icon icon="pencil" className="text-base" />
                          </button>
                          <button className="btn btn-icon border-default-300 hover:border-danger text-danger border" title="Stop collecting" onClick={() => cancel(row)}>
                            <Icon icon="x" className="text-base" />
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={6} className="text-default-400 py-4 text-center">
                  No deductions scheduled.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {editing && <EditChargeModal row={editing} onClose={() => setEditing(null)} />}
    </>
  )
}

const EditChargeModal = ({ row, onClose }: { row: ChargeRow; onClose: () => void }) => {
  const { data, setData, patch, processing, errors } = useForm({
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
              <label className="form-label">Amount per pay period</label>
              <input
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

const AdjustmentsTab = ({ adjustments }: { adjustments: AdjustmentRow[] }) => (
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
        {adjustments.length ? (
          adjustments.map((a) => (
            <tr key={a.id}>
              <td>{a.created_at ?? '—'}</td>
              <td className="font-medium">{a.item ?? '—'}</td>
              <td>
                <span
                  className={cn(
                    'badge badge-label',
                    a.type === 'incentive' ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger',
                  )}
                >
                  {a.type_label}
                </span>
              </td>
              <td className={cn('text-end font-medium', a.type === 'deduction' && 'text-danger')}>
                {a.type === 'deduction' ? `-${money(a.value)}` : money(a.value)}
              </td>
              <td>{a.is_billable ? 'Yes' : 'No'}</td>
              <td className="text-default-400">{a.notes ?? '—'}</td>
            </tr>
          ))
        ) : (
          <tr>
            <td colSpan={6} className="text-default-400 py-4 text-center">
              No adjustments yet.
            </td>
          </tr>
        )}
      </tbody>
    </table>
  </div>
)

const PtoTab = ({ pto }: { pto: PtoPayload }) => (
  <div>
    <p className="text-default-400 mb-4 text-sm">
      Anniversary year <span className="text-dark font-medium">{pto.year_start}</span> –{' '}
      <span className="text-dark font-medium">{pto.year_end}</span>
    </p>
    <div className="gap-base grid grid-cols-1 md:grid-cols-3">
      {pto.buckets.map((b) => (
        <div key={b.bucket} className="border-default-200 rounded-lg border p-4">
          <p className="text-default-400 text-2xs uppercase">{b.label}</p>
          <p className="text-dark mt-1 text-2xl font-semibold">
            {b.available}
            <span className="text-default-400 text-sm font-normal"> / {b.allotted} hrs</span>
          </p>
          <p className="text-default-400 text-xs">{b.used} used or pending</p>
        </div>
      ))}
    </div>
  </div>
)

const HistoryTab = ({ history }: { history: HistoryRow[] }) => (
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
        {history.length ? (
          history.map((h) => (
            <tr key={h.id}>
              <td className="text-nowrap">{h.created_at}</td>
              <td>
                {h.description}
                {h.event && <span className="text-default-400 ms-1.5 text-xs">({h.event})</span>}
              </td>
              <td>{h.causer ?? 'System'}</td>
            </tr>
          ))
        ) : (
          <tr>
            <td colSpan={3} className="text-default-400 py-4 text-center">
              No history yet.
            </td>
          </tr>
        )}
      </tbody>
    </table>
  </div>
)

// --- Page -------------------------------------------------------------------------

const Page = ({ person, workOrders, hours, adjustments, charges, pto, history }: Props) => {
  // The hiring fee is the one worth surfacing outside the tab — a cancelled or
  // fully-paid one still shows, so "no card" unambiguously means "no fee".
  const activeFee = charges?.find((c) => c.reason === 'hiring_fee') ?? null

  const tabs = [
    { key: 'work-orders', label: 'Work Orders', show: workOrders !== null },
    { key: 'hours', label: 'Hours', show: hours !== null },
    { key: 'adjustments', label: 'Adjustments', show: adjustments !== null },
    { key: 'charges', label: 'Deductions', show: charges !== null },
    { key: 'pto', label: 'Time Off', show: pto !== null },
    { key: 'history', label: 'History', show: history !== null },
  ].filter((t) => t.show)

  // Active tab lives in the URL hash so tabs are deep-linkable and survive refresh.
  const tabFromHash = () => {
    const hash = typeof window === 'undefined' ? '' : window.location.hash.slice(1)
    return tabs.some((t) => t.key === hash) ? hash : tabs[0].key
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

  return (
    <>
      <Head title={person.name} />
      <PageBreadcrumb title={person.name} subtitle="People" />

      <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
        <div className="space-y-6">
          <IdentityCard person={person} />
          {activeFee && <FeeCard charge={activeFee} />}
        </div>

        <div className="space-y-6 xl:col-span-2">
          <div className="card">
            <nav className="border-default-300 flex flex-wrap border-b px-4 pt-2" aria-label="Tabs" role="tablist">
              {tabs.map((t) => (
                <button
                  key={t.key}
                  type="button"
                  role="tab"
                  aria-selected={active === t.key}
                  onClick={() => selectTab(t.key)}
                  className={cn(
                    'hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden',
                    active === t.key ? 'border-primary text-primary border-b' : '',
                  )}
                >
                  {t.label}
                </button>
              ))}
            </nav>

            <div className="card-body p-6">
              {active === 'work-orders' && workOrders !== null && <WorkOrdersTab workOrders={workOrders} />}
              {active === 'hours' && hours !== null && <HoursTab hours={hours} />}
              {active === 'adjustments' && adjustments !== null && <AdjustmentsTab adjustments={adjustments} />}
              {active === 'charges' && charges !== null && <ChargesTab charges={charges} />}
              {active === 'pto' && pto !== null && <PtoTab pto={pto} />}
              {active === 'history' && history !== null && <HistoryTab history={history} />}
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
