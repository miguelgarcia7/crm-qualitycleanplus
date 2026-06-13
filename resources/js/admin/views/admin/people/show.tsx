import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'
import { useEffect, useState } from 'react'

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

type Props = {
  person: PersonInfo
  workOrders: WorkOrderRow[] | null
  hours: HoursRow[] | null
  adjustments: AdjustmentRow[] | null
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

const Page = ({ person, workOrders, hours, adjustments, pto, history }: Props) => {
  const tabs = [
    { key: 'work-orders', label: 'Work Orders', show: workOrders !== null },
    { key: 'hours', label: 'Hours', show: hours !== null },
    { key: 'adjustments', label: 'Adjustments', show: adjustments !== null },
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
