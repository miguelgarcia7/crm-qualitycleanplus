import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Stat, StatusTrack } from '@/components/timesheet/WeekSummary'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useEffect, useState } from 'react'

// --- Types -----------------------------------------------------------------

type Property = {
  id: number
  name: string
  pm_name: string | null
  pm_phone: string | null
  main_phone: string | null
  billing_email: string | null
  address: string | null
  city: string | null
  state: string | null
  zip: string | null
  timezone: string
  latitude: string | null
  longitude: string | null
  geofence_radius_meters: number
  qr_clock_enabled: boolean
  closing_day: number | null
  tax_rate: string | null
  status: string
  time_source: string
}

type Dept = { id: number; department_id: number; name: string | null; manager_name: string | null; manager_phone: string | null; is_active: boolean }
type Rate = {
  id: number
  position_id: number
  position: string | null
  pay_rate: number
  bill_rate: number
  ot_pay_rate: number
  ot_bill_rate: number
  effective_date: string | null
  end_date: string | null
  is_active: boolean
  notes: string | null
}
type Assignment = { id: number; person_id: number; person: string | null; role: string; role_label: string }
type Contract = {
  id: number
  name: string
  type: string
  type_label: string
  effective_date: string | null
  expiration_date: string | null
  uploaded_by: string | null
  is_active: boolean
  notes: string | null
}
type HistoryRow = { id: number; description: string; event: string | null; causer: string | null; created_at: string | null }
type PropertyHoliday = { id: number; name: string; type_label: string; this_year: string; enabled: boolean }
type JobCodeRow = { position_id: number; position: string; job_code: string | null }
type Option = { id: number; name: string }
type ValueLabel = { value: string; label: string }

type Can = {
  editProfile: boolean
  editDepartments: boolean
  editRates: boolean
  viewContracts: boolean
  editContracts: boolean
  downloadContracts: boolean
  manageAssignments: boolean
  inviteUsers: boolean
  editHolidays: boolean
  viewGrid: boolean
}

type WeekTotals = { people: number; total_minutes: number; overtime_minutes: number; total_pay: number; total_bill: number }
type ThisWeek = WeekTotals & { week_start: string; week_end: string; status: string | null; status_label: string | null; needs_look: number }
type WeekRow = WeekTotals & {
  id: number
  week_start: string
  week_end: string
  status: string
  status_label: string
  invoice_number: string | null
  sent_for_approval_at: string | null
}
/** Where to fix it: a URL, or a tab (and Settings section) on this page. */
type AttentionAction = { label: string; href?: string; tab?: string; section?: string }
type AttentionItem = { key: string; title: string; detail: string; action: AttentionAction }
type Overview = { thisWeek: ThisWeek | null; weeks: WeekRow[] | null; attention: AttentionItem[] }

type Props = {
  property: Property
  overview: Overview | null
  departments: Dept[]
  rates: Rate[]
  assignments: Assignment[]
  contracts: Contract[]
  history: HistoryRow[]
  holidays: PropertyHoliday[]
  jobCodes: JobCodeRow[]
  catalogs: {
    departments: Option[]
    positions: Option[]
    assignablePeople: Option[]
    contractTypes: ValueLabel[]
    assignmentRoles: ValueLabel[]
  }
  can: Can
}

// --- Helpers ---------------------------------------------------------------

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const hrs = (minutes: number) => (minutes / 60).toFixed(2)
// Noon, so no timezone offset can push the date to a neighbouring day.
const monthDay = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
const weekRange = (start: string, end: string) => `${monthDay(start)} – ${monthDay(end)}`
const timezoneName = (tz: string) =>
  new Intl.DateTimeFormat('en-US', { timeZone: tz, timeZoneName: 'longGeneric' }).formatToParts(new Date()).find((p) => p.type === 'timeZoneName')?.value ?? tz

/** Same colours as the Timesheets list, so a status reads the same everywhere. */
const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  pending_approval: 'bg-info/15 text-info',
  approved: 'bg-success/15 text-success',
  invoiced: 'bg-success/15 text-success',
  invoice_sent: 'bg-success/15 text-success',
  declined: 'bg-danger/15 text-danger',
  voided: 'bg-secondary/15 text-secondary',
}

/** closing_day is the ISO weekday (1 = Mon … 7 = Sun) the property's work week ends on. */
const WEEKDAYS: Record<number, string> = {
  1: 'Monday',
  2: 'Tuesday',
  3: 'Wednesday',
  4: 'Thursday',
  5: 'Friday',
  6: 'Saturday',
  7: 'Sunday',
}

/**
 * Owns its own confirmation so all four call sites get one, and so the prompt
 * can name what is being removed — the old shared handler asked "Are you
 * sure?", which tells the reader nothing about what they clicked.
 */
const RemoveButton = ({ url, title, description }: { url: string; title: string; description: React.ReactNode }) => (
  <RemoveButtonInner
    title={title}
    onClick={() =>
      confirmAction({
        title,
        message: description,
        confirmLabel: 'Remove',
        onConfirm: () => router.delete(url, { preserveScroll: true }),
      })
    }
  />
)

const RemoveButtonInner = ({ title, onClick }: { title: string; onClick: () => void }) => (
  <button
    type="button"
    className="btn btn-icon border-default-300 hover:border-default-400 border"
    onClick={onClick}
    title={title}
  >
    <Icon icon="trash" className="text-base" />
  </button>
)

/** Icon fact row for the identity card (HRM staff-profile pattern). */
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

const CardHeading = ({ title, children, actions }: { title: string; children?: React.ReactNode; actions?: React.ReactNode }) => (
  <div className="card-header flex flex-wrap items-center justify-between gap-3">
    <div>
      <h4 className="card-title">{title}</h4>
      {children && <p className="text-default-400 mt-1 text-sm">{children}</p>}
    </div>
    {actions}
  </div>
)

// --- Header (under the page title, on every tab) -----------------------------

const PropertyHeader = ({ property, can }: { property: Property; can: Can }) => {
  const cityState = [property.city, property.state].filter(Boolean).join(', ')
  const facts = [[property.address, cityState, property.zip].filter(Boolean).join(', '), timezoneName(property.timezone), `Week ends ${WEEKDAYS[property.closing_day ?? 7]}`].filter(Boolean)

  return (
    <div className="mb-1 flex flex-wrap items-center justify-between gap-4">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
        <span className={cn('badge badge-label capitalize', property.status === 'active' ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')}>
          {property.status}
        </span>
        <span className={cn('badge badge-label', property.time_source === 'import' ? 'bg-info/15 text-info' : 'bg-primary/15 text-primary')}>
          {property.time_source === 'import' ? 'Hour import' : 'Clock-in'}
        </span>
        <p className="text-default-500">{facts.join(' · ')}</p>
      </div>

      <div className="flex flex-wrap gap-2">
        {property.time_source === 'clock_in' && (
          <Link href={`/admin/properties/${property.id}/qr`} className="btn border-default-300 hover:border-default-400 border">
            <Icon icon="qrcode" className="size-4" /> Clock-In QR
          </Link>
        )}
        {can.viewGrid && (
          <Link href={`/admin/properties/${property.id}/grid`} className="btn bg-primary hover:bg-primary-hover font-semibold text-white">
            <Icon icon="layout" className="size-4" /> Weekly Timesheet
          </Link>
        )}
      </div>
    </div>
  )
}

// --- Overview tab ----------------------------------------------------------------

/** This week's figures, mirroring the grid's summary bar. */
const ThisWeekCard = ({ property, week, showHistory }: { property: Property; week: ThisWeek; showHistory: boolean }) => {
  const margin = week.total_bill - week.total_pay

  return (
    <div className="card">
      <CardHeading title={`This week · ${weekRange(week.week_start, week.week_end)}, ${week.week_end.slice(0, 4)}`} actions={week.status && week.status_label && <StatusTrack status={week.status} label={week.status_label} />}>
        Times in {timezoneName(property.timezone)}
      </CardHeading>
      <div className="border-default-300 grid grid-cols-2 border-y lg:grid-cols-5">
        <Stat label="Hours" value={hrs(week.total_minutes)} note={`${week.people} ${week.people === 1 ? 'person' : 'people'} with hours`} />
        <Stat label="Overtime" value={hrs(week.overtime_minutes)} note={week.overtime_minutes ? 'Over 40 h this week' : 'Nobody over 40 h'} />
        <Stat label="Payroll" value={money(week.total_pay)} note="Before taxes" />
        <Stat
          label="Billable"
          value={money(week.total_bill)}
          note={`Margin ${money(margin)}${week.total_bill ? ` · ${((margin / week.total_bill) * 100).toFixed(1)}%` : ''}`}
        />
        <Stat
          label="Needs a look"
          value={week.needs_look ? String(week.needs_look) : 'All clear'}
          valueClass={week.needs_look ? 'text-warning' : 'text-success'}
          note={week.needs_look ? 'No clock-out, or GPS not verified' : 'No missing or flagged punches'}
          className="col-span-2 lg:col-span-1"
        />
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
        {showHistory ? (
          <Link href={`/admin/timesheets?property_id=${property.id}`} className="text-primary text-sm font-medium underline underline-offset-3">
            All weeks for this property
          </Link>
        ) : (
          <span />
        )}
        <Link href={`/admin/properties/${property.id}/grid`} className="btn bg-primary hover:bg-primary-hover font-semibold text-white">
          Open This Week <Icon icon="arrow-right" className="size-4" />
        </Link>
      </div>
    </div>
  )
}

const AttentionCard = ({ items, onGo }: { items: AttentionItem[]; onGo: (action: AttentionAction) => void }) => (
  <div className="card">
    <CardHeading title="Needs attention" actions={items.length > 0 && <span className="badge badge-label bg-warning/15 text-warning">{items.length}</span>} />
    <div className="card-body pt-2">
      {items.length === 0 ? (
        <p className="text-default-500 flex items-center gap-2 py-3">
          <Icon icon="circle-check" className="text-success size-5" /> Nothing needs attention right now.
        </p>
      ) : (
        <ul className="divide-default-200 divide-y">
          {items.map((item) => (
            <li key={item.key} className="flex items-start gap-3 py-3.5">
              <div className="btn btn-icon bg-warning/15 size-8! shrink-0">
                <Icon icon="alert-triangle" className="text-warning text-lg" />
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-default-900 font-semibold">{item.title}</p>
                <p className="text-default-500 text-sm">{item.detail}</p>
              </div>
              {item.action.href ? (
                <Link href={item.action.href} className="btn btn-sm btn-light shrink-0">
                  {item.action.label}
                </Link>
              ) : (
                <button type="button" className="btn btn-sm btn-light shrink-0" onClick={() => onGo(item.action)}>
                  {item.action.label}
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  </div>
)

const PropertyCard = ({ property, can }: { property: Property; can: Can }) => {
  const cityState = [property.city, property.state].filter(Boolean).join(', ')
  const fullAddress = [property.address, cityState, property.zip].filter(Boolean).join(', ')
  const taxRate = property.tax_rate !== null ? `${(parseFloat(property.tax_rate) * 100).toFixed(2)}%` : null
  const hasGeofence = property.latitude !== null && property.longitude !== null

  return (
    <div className="card">
      <CardHeading
        title="Property"
        actions={
          can.editProfile && (
            <Link href={`/admin/properties/${property.id}/edit`} className="text-primary text-sm font-medium underline underline-offset-3">
              Edit
            </Link>
          )
        }
      />
      <div className="card-body flex flex-col gap-y-3">
        {fullAddress && (
          <FactRow icon="map-pin" label="Address">
            {fullAddress}
          </FactRow>
        )}
        {property.pm_name && (
          <FactRow icon="user-circle" label="PM">
            {property.pm_name}
            {property.pm_phone && ` · ${property.pm_phone}`}
          </FactRow>
        )}
        {property.main_phone && (
          <FactRow icon="phone-call" label="Hotel phone">
            {property.main_phone}
          </FactRow>
        )}
        {property.billing_email && (
          <FactRow icon="mail" label="Invoices to">
            {property.billing_email}
          </FactRow>
        )}
        {taxRate && (
          <FactRow icon="receipt-tax" label="Tax rate">
            {taxRate}
          </FactRow>
        )}
        {property.time_source === 'clock_in' && (
          <FactRow icon="map-pin-check" label="Geofence">
            {hasGeofence ? `${property.geofence_radius_meters} m radius${property.qr_clock_enabled ? ' · QR on' : ' · QR off'}` : 'Not set'}
          </FactRow>
        )}
      </div>
    </div>
  )
}

const RecentWeeksCard = ({ property, weeks }: { property: Property; weeks: WeekRow[] }) => (
  <div className="card">
    <CardHeading
      title="Recent weeks"
      actions={
        <Link href={`/admin/timesheets?property_id=${property.id}`} className="text-primary text-sm font-medium underline underline-offset-3">
          Full history in Timesheets
        </Link>
      }
    />
    <div className="table-wrapper">
      <table className="table table-hover">
        <thead className="thead-sm">
          <tr className="bg-light/25 text-xs uppercase">
            <th>Week</th>
            <th>Status</th>
            <th className="text-end">People</th>
            <th className="text-end">Hours</th>
            <th className="text-end">Billable</th>
            <th>Invoice</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {weeks.length ? (
            weeks.map((w) => (
              <tr key={w.id}>
                <td className="text-default-900 font-semibold whitespace-nowrap">{weekRange(w.week_start, w.week_end)}</td>
                <td>
                  <span className={cn('badge badge-label', statusBadge[w.status] ?? 'bg-light text-default-600')}>{w.status_label}</span>
                </td>
                <td className="text-end tabular-nums">{w.people}</td>
                <td className="text-end tabular-nums">{hrs(w.total_minutes)}</td>
                <td className="text-end tabular-nums">{money(w.total_bill)}</td>
                <td className="whitespace-nowrap">{w.invoice_number ?? '—'}</td>
                <td className="text-end">
                  <Link href={`/admin/properties/${property.id}/grid?week=${w.week_start}`} className="btn btn-sm btn-light">
                    Open Week
                  </Link>
                </td>
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={7} className="text-default-400 py-4 text-center">
                No weeks yet.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  </div>
)

const OverviewTab = ({ property, overview, can, onGo }: { property: Property; overview: Overview; can: Can; onGo: (action: AttentionAction) => void }) => (
  <div className="space-y-6">
    {overview.thisWeek && <ThisWeekCard property={property} week={overview.thisWeek} showHistory={overview.weeks !== null} />}

    <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
      <div className="xl:col-span-2">
        <AttentionCard items={overview.attention} onGo={onGo} />
      </div>
      <PropertyCard property={property} can={can} />
    </div>

    {overview.weeks && <RecentWeeksCard property={property} weeks={overview.weeks} />}
  </div>
)

// --- Tab sections ----------------------------------------------------------

const DepartmentsTab = ({ property, departments, catalogs, can }: Pick<Props, 'property' | 'departments' | 'catalogs' | 'can'>) => {
  const { data, setData, post, processing, errors, reset } = useForm({
    department_id: '',
    manager_name: '',
    manager_phone: '',
    is_active: true,
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/properties/${property.id}/departments`, { preserveScroll: true, onSuccess: () => reset() })
  }

  return (
    <div className="space-y-6">
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-xs uppercase">
              <th>Department</th>
              <th>Manager</th>
              <th>Phone</th>
              <th>Active</th>
              {can.editDepartments && <th></th>}
            </tr>
          </thead>
          <tbody>
            {departments.length ? (
              departments.map((d) => (
                <tr key={d.id}>
                  <td className="font-medium">{d.name}</td>
                  <td>{d.manager_name ?? '—'}</td>
                  <td>{d.manager_phone ?? '—'}</td>
                  <td>
                    <span className={cn('badge badge-label', d.is_active ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')}>
                      {d.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  {can.editDepartments && (
                    <td className="text-end">
                      <RemoveButton url={`/admin/properties/${property.id}/departments/${d.id}`} title="Remove department" description={<>Remove <strong>{d.name}</strong> from this property? Its manager contact goes with it.</>} />
                    </td>
                  )}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={5} className="text-default-400 py-4 text-center">
                  No departments yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {can.editDepartments && (
        <form onSubmit={submit} className="bg-light/20 grid grid-cols-1 gap-4 rounded-xl p-4 md:grid-cols-4">
          <div>
            <label className="form-label">Department</label>
            <select className="form-select" value={data.department_id} onChange={(e) => setData('department_id', e.target.value)} required>
              <option value="">Select…</option>
              {catalogs.departments.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.name}
                </option>
              ))}
            </select>
            {errors.department_id && <p className="text-danger mt-1 text-sm">{errors.department_id}</p>}
          </div>
          <div>
            <label className="form-label">Manager</label>
            <input className="form-input" value={data.manager_name} onChange={(e) => setData('manager_name', e.target.value)} />
          </div>
          <div>
            <label className="form-label">Phone</label>
            <input className="form-input" value={data.manager_phone} onChange={(e) => setData('manager_phone', e.target.value)} />
          </div>
          <div className="flex items-end">
            <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>
              Add
            </button>
          </div>
        </form>
      )}
    </div>
  )
}

const otFromBase = (value: string) => {
  const n = parseFloat(value)
  return isNaN(n) ? '' : (n * 1.5).toFixed(2)
}

const RatesTab = ({ property, rates, jobCodes, catalogs, can }: Pick<Props, 'property' | 'rates' | 'jobCodes' | 'catalogs' | 'can'>) => {
  const { data, setData, post, processing, errors, reset } = useForm({
    position_id: '',
    pay_rate: '',
    bill_rate: '',
    ot_pay_rate: '',
    ot_bill_rate: '',
    effective_date: '',
    notes: '',
  })

  // OT is 1.5× by default; the override toggle unlocks the fields for the rare
  // contract that stipulates a different overtime multiplier.
  const [overrideOt, setOverrideOt] = useState(false)
  useEffect(() => {
    if (overrideOt) return
    setData((d) => ({ ...d, ot_pay_rate: otFromBase(d.pay_rate), ot_bill_rate: otFromBase(d.bill_rate) }))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data.pay_rate, data.bill_rate, overrideOt])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/properties/${property.id}/rates`, {
      preserveScroll: true,
      onSuccess: () => {
        reset()
        setOverrideOt(false)
      },
    })
  }

  return (
    <div className="space-y-6">
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-xs uppercase">
              <th>Position</th>
              <th>Pay</th>
              <th>Bill</th>
              <th>OT Pay</th>
              <th>OT Bill</th>
              <th>Effective</th>
              <th>End</th>
              {can.editRates && <th></th>}
            </tr>
          </thead>
          <tbody>
            {rates.length ? (
              rates.map((r) => (
                <tr key={r.id}>
                  <td className="font-medium">{r.position}</td>
                  <td>{money(r.pay_rate)}</td>
                  <td>{money(r.bill_rate)}</td>
                  <td>{money(r.ot_pay_rate)}</td>
                  <td>{money(r.ot_bill_rate)}</td>
                  <td>{r.effective_date}</td>
                  <td>{r.end_date ?? <span className="badge badge-label bg-success/15 text-success">current</span>}</td>
                  {can.editRates && (
                    <td className="text-end">
                      <RemoveButton url={`/admin/properties/${property.id}/rates/${r.id}`} title="Remove rate" description={<>Remove the <strong>{r.position}</strong> rate? New work orders will have no rate to auto-fill from. Existing ones keep their snapshot.</>} />
                    </td>
                  )}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={8} className="text-default-400 py-4 text-center">
                  No rates yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {can.editRates && (
        <form onSubmit={submit} className="bg-light/20 grid grid-cols-2 gap-4 rounded-xl p-4 md:grid-cols-4">
          <div className="col-span-2 md:col-span-1">
            <label className="form-label">Position</label>
            <select className="form-select" value={data.position_id} onChange={(e) => setData('position_id', e.target.value)} required>
              <option value="">Select…</option>
              {catalogs.positions.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.name}
                </option>
              ))}
            </select>
            {errors.position_id && <p className="text-danger mt-1 text-sm">{errors.position_id}</p>}
          </div>
          <div>
            <label className="form-label">Pay ($)</label>
            <input className="form-input" value={data.pay_rate} onChange={(e) => setData('pay_rate', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">Bill ($)</label>
            <input className="form-input" value={data.bill_rate} onChange={(e) => setData('bill_rate', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">OT Pay ($){!overrideOt && <span className="text-default-400"> · auto 1.5×</span>}</label>
            <input
              className={cn('form-input', !overrideOt && 'opacity-60')}
              value={data.ot_pay_rate}
              onChange={(e) => setData('ot_pay_rate', e.target.value)}
              readOnly={!overrideOt}
              tabIndex={overrideOt ? undefined : -1}
              required
            />
            {errors.ot_pay_rate && <p className="text-danger mt-1 text-sm">{errors.ot_pay_rate}</p>}
          </div>
          <div>
            <label className="form-label">OT Bill ($){!overrideOt && <span className="text-default-400"> · auto 1.5×</span>}</label>
            <input
              className={cn('form-input', !overrideOt && 'opacity-60')}
              value={data.ot_bill_rate}
              onChange={(e) => setData('ot_bill_rate', e.target.value)}
              readOnly={!overrideOt}
              tabIndex={overrideOt ? undefined : -1}
              required
            />
            {errors.ot_bill_rate && <p className="text-danger mt-1 text-sm">{errors.ot_bill_rate}</p>}
          </div>
          <div className="col-span-2 -mt-2 md:col-span-4">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="form-checkbox"
                checked={overrideOt}
                onChange={(e) => setOverrideOt(e.target.checked)}
              />
              Override OT rates — this property&apos;s contract differs from the standard 1.5×
            </label>
          </div>
          <div>
            <label className="form-label">Effective Date</label>
            <input type="date" className="form-input" value={data.effective_date} onChange={(e) => setData('effective_date', e.target.value)} required />
            {errors.effective_date && <p className="text-danger mt-1 text-sm">{errors.effective_date}</p>}
          </div>
          <div className="flex items-end">
            <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>
              Add Rate
            </button>
          </div>
        </form>
      )}

      <JobCodesCard property={property} jobCodes={jobCodes} canEdit={can.editRates} />
    </div>
  )
}

// The client's own accounting codes per position — printed on their invoices
// and exports so their accounting team can post the cost (e.g. "1001-10").
const JobCodesCard = ({ property, jobCodes, canEdit }: { property: Property; jobCodes: JobCodeRow[]; canEdit: boolean }) => {
  const [codes, setCodes] = useState<Record<number, string>>(
    Object.fromEntries(jobCodes.map((r) => [r.position_id, r.job_code ?? ''])),
  )
  const [saving, setSaving] = useState(false)

  const initial = JSON.stringify(jobCodes.map((r) => r.job_code ?? ''))
  const dirty = JSON.stringify(jobCodes.map((r) => codes[r.position_id] ?? '')) !== initial

  const save = () => {
    setSaving(true)
    router.put(
      `/admin/properties/${property.id}/job-codes`,
      { codes: jobCodes.map((r) => ({ position_id: r.position_id, job_code: codes[r.position_id] ?? '' })) },
      { preserveScroll: true, onFinish: () => setSaving(false) },
    )
  }

  if (jobCodes.length === 0) return null

  return (
    <div className="border-default-200 border-t pt-5">
      <h5 className="font-semibold">Job Codes</h5>
      <p className="text-default-400 mb-3 text-sm">
        This property&apos;s accounting code per position — printed next to the position on invoices and exports.
      </p>
      <div className="max-w-lg space-y-2">
        {jobCodes.map((r) => (
          <div key={r.position_id} className="flex items-center gap-3">
            <span className="w-48 text-sm font-medium">{r.position}</span>
            <input
              className="form-input flex-1"
              placeholder="e.g. 1001-10"
              value={codes[r.position_id] ?? ''}
              onChange={(e) => setCodes((c) => ({ ...c, [r.position_id]: e.target.value }))}
              readOnly={!canEdit}
            />
          </div>
        ))}
      </div>
      {canEdit && (
        <button
          type="button"
          className="btn bg-primary hover:bg-primary-hover mt-3 px-4 py-2 font-semibold text-white disabled:opacity-50"
          onClick={save}
          disabled={!dirty || saving}
        >
          Save Job Codes
        </button>
      )}
    </div>
  )
}

const ContractsTab = ({ property, contracts, catalogs, can }: Pick<Props, 'property' | 'contracts' | 'catalogs' | 'can'>) => {
  const { data, setData, post, processing, errors, reset } = useForm<{
    name: string
    type: string
    effective_date: string
    expiration_date: string
    notes: string
    document: File | null
  }>({
    name: '',
    type: 'msa',
    effective_date: '',
    expiration_date: '',
    notes: '',
    document: null,
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/properties/${property.id}/contracts`, { preserveScroll: true, forceFormData: true, onSuccess: () => reset() })
  }

  return (
    <div className="space-y-6">
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-xs uppercase">
              <th>Name</th>
              <th>Type</th>
              <th>Effective</th>
              <th>Expires</th>
              <th>Uploaded By</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {contracts.length ? (
              contracts.map((c) => (
                <tr key={c.id}>
                  <td className="font-medium">{c.name}</td>
                  <td>{c.type_label}</td>
                  <td>{c.effective_date ?? '—'}</td>
                  <td>{c.expiration_date ?? '—'}</td>
                  <td>{c.uploaded_by ?? '—'}</td>
                  <td className="text-end">
                    <div className="flex justify-end gap-1.5">
                      {can.downloadContracts && (
                        <a
                          href={`/admin/properties/${property.id}/contracts/${c.id}/download`}
                          className="btn btn-icon border-default-300 hover:border-default-400 border"
                          title="Download contract"
                        >
                          <Icon icon="download" className="text-base" />
                        </a>
                      )}
                      {can.editContracts && (
                        <RemoveButton url={`/admin/properties/${property.id}/contracts/${c.id}`} title="Delete contract" description={<>Delete <strong>{c.name}</strong>? The uploaded document is removed too.</>} />
                      )}
                    </div>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={6} className="text-default-400 py-4 text-center">
                  No contracts yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {can.editContracts && (
        <form onSubmit={submit} className="bg-light/20 grid grid-cols-1 gap-4 rounded-xl p-4 md:grid-cols-2">
          <div>
            <label className="form-label">Document Name</label>
            <input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            {errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}
          </div>
          <div>
            <label className="form-label">Type</label>
            <select className="form-select" value={data.type} onChange={(e) => setData('type', e.target.value)}>
              {catalogs.contractTypes.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="form-label">Effective Date</label>
            <input type="date" className="form-input" value={data.effective_date} onChange={(e) => setData('effective_date', e.target.value)} />
          </div>
          <div>
            <label className="form-label">Expiration Date</label>
            <input type="date" className="form-input" value={data.expiration_date} onChange={(e) => setData('expiration_date', e.target.value)} />
            {errors.expiration_date && <p className="text-danger mt-1 text-sm">{errors.expiration_date}</p>}
          </div>
          <div className="md:col-span-2">
            <label className="form-label">Document (PDF/DOC, max 20 MB)</label>
            <input type="file" className="form-input" accept=".pdf,.doc,.docx" onChange={(e) => setData('document', e.target.files?.[0] ?? null)} required />
            {errors.document && <p className="text-danger mt-1 text-sm">{errors.document}</p>}
          </div>
          <div>
            <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>
              Upload Contract
            </button>
          </div>
        </form>
      )}
    </div>
  )
}

const TeamTab = ({ property, assignments, catalogs, can }: Pick<Props, 'property' | 'assignments' | 'catalogs' | 'can'>) => {
  const { data, setData, post, processing, errors, reset } = useForm({
    person_id: '',
    role: 'recruiter',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/properties/${property.id}/assignments`, { preserveScroll: true, onSuccess: () => reset() })
  }

  return (
    <div className="space-y-6">
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-xs uppercase">
              <th>Person</th>
              <th>Role</th>
              {can.manageAssignments && <th></th>}
            </tr>
          </thead>
          <tbody>
            {assignments.length ? (
              assignments.map((a) => (
                <tr key={a.id}>
                  <td className="font-medium">
                    <Link href={`/admin/people/${a.person_id}`} className="hover:text-primary">
                      {a.person}
                    </Link>
                  </td>
                  <td>{a.role_label}</td>
                  {can.manageAssignments && (
                    <td className="text-end">
                      <RemoveButton url={`/admin/properties/${property.id}/assignments/${a.id}`} title="Remove assignment" description={<>Unassign <strong>{a.person}</strong> ({a.role_label}) from this property?</>} />
                    </td>
                  )}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={3} className="text-default-400 py-4 text-center">
                  No one assigned yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {can.manageAssignments && (
        <form onSubmit={submit} className="bg-light/20 grid grid-cols-1 gap-4 rounded-xl p-4 md:grid-cols-3">
          <div>
            <label className="form-label">Person</label>
            <select className="form-select" value={data.person_id} onChange={(e) => setData('person_id', e.target.value)} required>
              <option value="">Select…</option>
              {catalogs.assignablePeople.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.name}
                </option>
              ))}
            </select>
            {errors.person_id && <p className="text-danger mt-1 text-sm">{errors.person_id}</p>}
          </div>
          <div>
            <label className="form-label">Role</label>
            <select className="form-select" value={data.role} onChange={(e) => setData('role', e.target.value)}>
              {catalogs.assignmentRoles.map((r) => (
                <option key={r.value} value={r.value}>
                  {r.label}
                </option>
              ))}
            </select>
            {errors.role && <p className="text-danger mt-1 text-sm">{errors.role}</p>}
          </div>
          <div className="flex items-end">
            <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>
              Assign
            </button>
          </div>
        </form>
      )}

      {can.inviteUsers && (
        <p className="text-default-400 text-sm">
          Person not in the list?{' '}
          <Link href={`/admin/people/invite?property_id=${property.id}`} className="text-primary underline">
            Invite a new property user
          </Link>{' '}
          — they&apos;ll get an email to set their password.
        </p>
      )}
    </div>
  )
}

const HolidaysTab = ({ property, holidays, can }: Pick<Props, 'property' | 'holidays' | 'can'>) => {
  const [enabledIds, setEnabledIds] = useState<number[]>(holidays.filter((h) => h.enabled).map((h) => h.id))
  const [saving, setSaving] = useState(false)

  const initial = holidays
    .filter((h) => h.enabled)
    .map((h) => h.id)
    .sort()
    .join(',')
  const dirty = [...enabledIds].sort().join(',') !== initial

  const toggle = (id: number) => {
    setEnabledIds((ids) => (ids.includes(id) ? ids.filter((i) => i !== id) : [...ids, id]))
  }

  const save = () => {
    setSaving(true)
    router.put(
      `/admin/properties/${property.id}/holidays`,
      { holiday_ids: enabledIds },
      { preserveScroll: true, onFinish: () => setSaving(false) },
    )
  }

  return (
    <div className="space-y-6">
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-xs uppercase">
              <th>Holiday</th>
              <th>Type</th>
              <th>This Year</th>
              <th className="text-center">Observed</th>
            </tr>
          </thead>
          <tbody>
            {holidays.length ? (
              holidays.map((h) => (
                <tr key={h.id}>
                  <td className="font-medium">{h.name}</td>
                  <td>
                    <span
                      className={cn(
                        'badge badge-label',
                        h.type_label === 'Legal' ? 'bg-info/15 text-info' : 'bg-secondary/15 text-secondary',
                      )}
                    >
                      {h.type_label}
                    </span>
                  </td>
                  <td>{h.this_year}</td>
                  <td className="text-center">
                    <input
                      type="checkbox"
                      className="form-checkbox"
                      checked={enabledIds.includes(h.id)}
                      onChange={() => toggle(h.id)}
                      disabled={!can.editHolidays}
                    />
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={4} className="text-default-400 py-4 text-center">
                  No holidays in the calendar yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {can.editHolidays && (
        <div className="flex items-center gap-3">
          <button
            type="button"
            className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white disabled:opacity-50"
            onClick={save}
            disabled={!dirty || saving}
          >
            Save Holidays
          </button>
          <p className="text-default-400 text-sm">
            Work on an observed holiday pays and bills at the holiday rate and never counts as overtime. Changes recompute the current open
            weeks; invoiced weeks are frozen.
          </p>
        </div>
      )}
    </div>
  )
}

const HistoryTab = ({ history }: { history: HistoryRow[] }) => (
  <div className="table-wrapper">
    <table className="table table-hover">
      <thead className="thead-sm">
        <tr className="bg-light/25 text-xs uppercase">
          <th>When</th>
          <th>Change</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        {history.length ? (
          history.map((h) => (
            <tr key={h.id}>
              <td className="whitespace-nowrap">{h.created_at}</td>
              <td>{h.description}</td>
              <td>{h.causer ?? '—'}</td>
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

// --- Settings tab ----------------------------------------------------------------

type SectionKey = 'contacts' | 'holidays' | 'contracts' | 'history'

const SettingsTab = ({
  section: requested,
  onSection,
  ...props
}: Pick<Props, 'property' | 'departments' | 'assignments' | 'contracts' | 'history' | 'holidays' | 'catalogs' | 'can'> & {
  section: SectionKey
  onSection: (key: SectionKey) => void
}) => {
  const { property, can } = props
  const sections: { key: SectionKey; label: string; show: boolean }[] = [
    { key: 'contacts', label: 'Contacts', show: true },
    { key: 'holidays', label: 'Holidays', show: true },
    { key: 'contracts', label: 'Contracts', show: can.viewContracts },
    { key: 'history', label: 'History', show: true },
  ]
  // A link to a section this viewer can't see lands on the first one instead.
  const section = sections.some((s) => s.key === requested && s.show) ? requested : 'contacts'

  return (
    <div className="gap-base grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)]">
      <nav className="flex flex-wrap gap-1 lg:flex-col" aria-label="Settings sections">
        {sections
          .filter((s) => s.show)
          .map((s) => (
            <button
              key={s.key}
              type="button"
              aria-current={section === s.key ? 'page' : undefined}
              onClick={() => onSection(s.key)}
              className={cn(
                'rounded-lg px-3 py-2.5 text-start font-medium',
                section === s.key ? 'bg-light text-default-900 font-semibold' : 'text-default-600 hover:bg-light/60 hover:text-default-900',
              )}
            >
              {s.label}
            </button>
          ))}
      </nav>

      <div className="min-w-0 space-y-6">
        {section === 'contacts' && (
          <>
            <div className="card">
              <CardHeading title="Departments">Each department at the hotel and the manager who runs it.</CardHeading>
              <div className="card-body">
                <DepartmentsTab property={property} departments={props.departments} catalogs={props.catalogs} can={can} />
              </div>
            </div>
            <div className="card">
              <CardHeading title="Our team on this property">Recruiters and property users who can see and work this property.</CardHeading>
              <div className="card-body">
                <TeamTab property={property} assignments={props.assignments} catalogs={props.catalogs} can={can} />
              </div>
            </div>
          </>
        )}
        {section === 'holidays' && (
          <div className="card">
            <CardHeading title="Holidays observed">Which holidays this property pays and bills at the holiday rate.</CardHeading>
            <div className="card-body">
              <HolidaysTab property={property} holidays={props.holidays} can={can} />
            </div>
          </div>
        )}
        {section === 'contracts' && can.viewContracts && (
          <div className="card">
            <CardHeading title="Contracts">Signed agreements, rate sheets and amendments for this property.</CardHeading>
            <div className="card-body">
              <ContractsTab property={property} contracts={props.contracts} catalogs={props.catalogs} can={can} />
            </div>
          </div>
        )}
        {section === 'history' && (
          <div className="card">
            <CardHeading title="History">Every change to this property&apos;s setup.</CardHeading>
            <div className="card-body">
              <HistoryTab history={props.history} />
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

// --- Page ------------------------------------------------------------------

type TabKey = 'overview' | 'rates' | 'settings'

const TABS: { key: TabKey; label: string }[] = [
  { key: 'overview', label: 'Overview' },
  { key: 'rates', label: 'Rates & Job Codes' },
  { key: 'settings', label: 'Settings' },
]

/** Hashes from the old single-card layout, so saved links still land on the right section. */
const LEGACY_HASHES: Record<string, string> = {
  departments: 'settings/contacts',
  team: 'settings/contacts',
  holidays: 'settings/holidays',
  contracts: 'settings/contracts',
  history: 'settings/history',
}

const SECTIONS: SectionKey[] = ['contacts', 'holidays', 'contracts', 'history']

/** The URL hash holds the tab, and for Settings its section: #overview, #settings/holidays. */
const locationFromHash = (): { tab: TabKey; section: SectionKey } => {
  const raw = typeof window === 'undefined' ? '' : window.location.hash.slice(1)
  const [tab, section] = (LEGACY_HASHES[raw] ?? raw).split('/')

  return {
    tab: TABS.some((t) => t.key === tab) ? (tab as TabKey) : 'overview',
    section: SECTIONS.includes(section as SectionKey) ? (section as SectionKey) : 'contacts',
  }
}

const Page = ({ property, overview, departments, rates, assignments, contracts, history, holidays, jobCodes, catalogs, can }: Props) => {
  const [location, setLocation] = useState(locationFromHash)

  useEffect(() => {
    const onHashChange = () => setLocation(locationFromHash())
    window.addEventListener('hashchange', onHashChange)
    return () => window.removeEventListener('hashchange', onHashChange)
  }, [])

  const go = (tab: TabKey, section: SectionKey = location.section) => {
    setLocation({ tab, section })
    window.history.replaceState(null, '', tab === 'settings' ? `#settings/${section}` : `#${tab}`)
  }

  const goToAction = (action: AttentionAction) => {
    if (TABS.some((t) => t.key === action.tab)) {
      go(action.tab as TabKey, SECTIONS.includes(action.section as SectionKey) ? (action.section as SectionKey) : undefined)
      // The card that sent us is lower down; start the new tab from its top.
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  return (
    <>
      <Head title={property.name} />
      <PageBreadcrumb title={property.name} subtitle="Property Bible" />
      <PropertyHeader property={property} can={can} />

      {/* Tabs sit under the page title, full width, as on My Profile and the System Reference pages. */}
      <nav className="border-default-300 mt-4 mb-5 flex flex-wrap gap-1 border-b" aria-label="Property sections" role="tablist">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            role="tab"
            aria-selected={location.tab === t.key}
            onClick={() => go(t.key)}
            className={cn(
              '-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 font-medium focus:outline-hidden',
              location.tab === t.key ? 'border-primary text-default-900' : 'text-default-500 hover:text-default-900 border-transparent',
            )}
          >
            {t.label}
          </button>
        ))}
      </nav>

      {location.tab === 'overview' && overview && <OverviewTab property={property} overview={overview} can={can} onGo={goToAction} />}

      {location.tab === 'rates' && (
        <div className="card">
          <CardHeading title="Positions, rates & job codes">Current rates fill new work orders. Job codes print next to the position on invoices and exports.</CardHeading>
          <div className="card-body">
            <RatesTab property={property} rates={rates} jobCodes={jobCodes} catalogs={catalogs} can={can} />
          </div>
        </div>
      )}

      {location.tab === 'settings' && (
        <SettingsTab
          section={location.section}
          onSection={(key) => go('settings', key)}
          property={property}
          departments={departments}
          assignments={assignments}
          contracts={contracts}
          history={history}
          holidays={holidays}
          catalogs={catalogs}
          can={can}
        />
      )}
    </>
  )
}

export default Page
