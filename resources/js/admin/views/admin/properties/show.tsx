import PageBreadcrumb from '@/components/PageBreadcrumb'
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
  address: string | null
  city: string | null
  state: string | null
  zip: string | null
  timezone: string
  latitude: string | null
  longitude: string | null
  geofence_radius_meters: number
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
}

type Props = {
  property: Property
  departments: Dept[]
  rates: Rate[]
  assignments: Assignment[]
  contracts: Contract[]
  history: HistoryRow[]
  holidays: PropertyHoliday[]
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

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`

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

const confirmDelete = (url: string) => {
  if (confirm('Are you sure?')) {
    router.delete(url, { preserveScroll: true })
  }
}

const RemoveButton = ({ url, title }: { url: string; title: string }) => (
  <button
    type="button"
    className="btn btn-icon border-default-300 hover:border-default-400 border"
    onClick={() => confirmDelete(url)}
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

// --- Identity card (left column) --------------------------------------------

const IdentityCard = ({ property, can }: { property: Property; can: Can }) => {
  const initials = property.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

  const cityState = [property.city, property.state].filter(Boolean).join(', ')
  const fullAddress = [property.address, cityState, property.zip].filter(Boolean).join(', ')
  const taxRate = property.tax_rate !== null ? `${(parseFloat(property.tax_rate) * 100).toFixed(2)}%` : null
  const hasGeofence = property.latitude !== null && property.longitude !== null

  return (
    <div className="card">
      <div className="card-body">
        <div className="mb-7.5 flex items-center justify-between">
          <div className="gap-base flex items-center">
            <div className="bg-primary/10 text-primary flex size-18 shrink-0 items-center justify-center rounded-full text-xl font-semibold">
              {initials}
            </div>
            <div>
              <h5 className="font-medium">{property.name}</h5>
              <p className="text-default-400 mb-3">{cityState || 'No location set'}</p>
              <div className="flex flex-wrap gap-1.5">
                <span
                  className={cn(
                    'badge badge-label capitalize',
                    property.status === 'active' ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary',
                  )}
                >
                  {property.status}
                </span>
                <span
                  className={cn(
                    'badge badge-label',
                    property.time_source === 'import' ? 'bg-info/15 text-info' : 'bg-primary/15 text-primary',
                  )}
                >
                  {property.time_source === 'import' ? 'Hour import' : 'Clock-in'}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div className="flex flex-col gap-y-3">
          {fullAddress && (
            <FactRow icon="map-pin" label="Address">
              {fullAddress}
            </FactRow>
          )}
          {property.pm_name && (
            <FactRow icon="user-circle" label="PM">
              {property.pm_name}
            </FactRow>
          )}
          {property.pm_phone && (
            <FactRow icon="phone" label="PM phone">
              {property.pm_phone}
            </FactRow>
          )}
          {property.main_phone && (
            <FactRow icon="phone-call" label="Hotel phone">
              {property.main_phone}
            </FactRow>
          )}
          <FactRow icon="clock" label="Timezone">
            {property.timezone}
          </FactRow>
          {taxRate && (
            <FactRow icon="receipt-tax" label="Tax rate">
              {taxRate}
            </FactRow>
          )}
          <FactRow icon="calendar" label="Week ends">
            {WEEKDAYS[property.closing_day ?? 7]}
          </FactRow>
          <FactRow icon="map-pin-check" label="Geofence">
            {hasGeofence ? `Set (${property.geofence_radius_meters} m radius)` : 'Not set — QR clock-in disabled'}
          </FactRow>
        </div>

        <div className="mt-6 flex flex-col gap-2">
          <Link
            href={`/admin/properties/${property.id}/grid`}
            className="btn bg-primary hover:bg-primary-hover justify-center font-semibold text-white"
          >
            <Icon icon="layout" className="me-1.5 size-4" /> Weekly Timesheet
          </Link>
          <div className="flex gap-2">
            <Link href={`/admin/properties/${property.id}/qr`} className="btn btn-light flex-1 justify-center">
              <Icon icon="qrcode" className="me-1.5 size-4" /> Clock-In QR
            </Link>
            {can.editProfile && (
              <Link href={`/admin/properties/${property.id}/edit`} className="btn btn-light flex-1 justify-center">
                <Icon icon="edit" className="me-1.5 size-4" /> Edit
              </Link>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

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
                      <RemoveButton url={`/admin/properties/${property.id}/departments/${d.id}`} title="Remove department" />
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

const RatesTab = ({ property, rates, catalogs, can }: Pick<Props, 'property' | 'rates' | 'catalogs' | 'can'>) => {
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
                      <RemoveButton url={`/admin/properties/${property.id}/rates/${r.id}`} title="Remove rate" />
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
                        <RemoveButton url={`/admin/properties/${property.id}/contracts/${c.id}`} title="Delete contract" />
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
                      <RemoveButton url={`/admin/properties/${property.id}/assignments/${a.id}`} title="Remove assignment" />
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

// --- Page ------------------------------------------------------------------

const Page = ({ property, departments, rates, assignments, contracts, history, holidays, catalogs, can }: Props) => {
  const tabs = [
    { key: 'rates', label: 'Positions & Rates', show: true },
    { key: 'departments', label: 'Departments', show: true },
    { key: 'holidays', label: 'Holidays', show: true },
    { key: 'team', label: 'Team', show: true },
    { key: 'contracts', label: 'Contracts', show: can.viewContracts },
    { key: 'history', label: 'History', show: true },
  ].filter((t) => t.show)

  // Active tab lives in the URL hash (#rates, #team, …) so tabs are
  // deep-linkable and survive refresh. Invalid/missing hash → first tab.
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
      <Head title={property.name} />
      <PageBreadcrumb title={property.name} subtitle="Property Bible" />

      <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
        <div className="space-y-6">
          <IdentityCard property={property} can={can} />
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
              {active === 'rates' && <RatesTab property={property} rates={rates} catalogs={catalogs} can={can} />}
              {active === 'departments' && <DepartmentsTab property={property} departments={departments} catalogs={catalogs} can={can} />}
              {active === 'holidays' && <HolidaysTab property={property} holidays={holidays} can={can} />}
              {active === 'team' && <TeamTab property={property} assignments={assignments} catalogs={catalogs} can={can} />}
              {active === 'contracts' && can.viewContracts && <ContractsTab property={property} contracts={contracts} catalogs={catalogs} can={can} />}
              {active === 'history' && <HistoryTab history={history} />}
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
