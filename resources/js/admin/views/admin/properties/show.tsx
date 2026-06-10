import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
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
}

type Props = {
  property: Property
  departments: Dept[]
  rates: Rate[]
  assignments: Assignment[]
  contracts: Contract[]
  history: HistoryRow[]
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

const confirmDelete = (url: string) => {
  if (confirm('Are you sure?')) {
    router.delete(url, { preserveScroll: true })
  }
}

// --- Tab sections ----------------------------------------------------------

const ProfileTab = ({ property, can }: { property: Property; can: Can }) => {
  const rows: [string, string | number | null][] = [
    ['PM Name', property.pm_name],
    ['PM Phone', property.pm_phone],
    ['Hotel Main Phone', property.main_phone],
    ['Address', property.address],
    ['City', property.city],
    ['State', property.state],
    ['Zip', property.zip],
    ['Timezone', property.timezone],
    ['Latitude', property.latitude],
    ['Longitude', property.longitude],
    ['Geofence Radius (m)', property.geofence_radius_meters],
    ['Closing Day', property.closing_day],
    ['Tax Rate', property.tax_rate],
  ]
  return (
    <div>
      {can.editProfile && (
        <div className="mb-4 flex justify-end">
          <Link href={`/admin/properties/${property.id}/edit`} className="btn btn-light px-4 py-2">
            Edit Profile
          </Link>
        </div>
      )}
      <dl className="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
        {rows.map(([label, value]) => (
          <div key={label} className="flex justify-between border-b border-dashed py-2">
            <dt className="text-default-500">{label}</dt>
            <dd className="font-medium">{value ?? '—'}</dd>
          </div>
        ))}
      </dl>
    </div>
  )
}

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
            <tr className="bg-light/25 text-2xs uppercase">
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
                  <td>{d.is_active ? 'Yes' : 'No'}</td>
                  {can.editDepartments && (
                    <td className="text-end">
                      <button className="text-danger text-sm hover:underline" onClick={() => confirmDelete(`/admin/properties/${property.id}/departments/${d.id}`)}>
                        Remove
                      </button>
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

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/properties/${property.id}/rates`, { preserveScroll: true, onSuccess: () => reset() })
  }

  return (
    <div className="space-y-6">
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-2xs uppercase">
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
                      <button className="text-danger text-sm hover:underline" onClick={() => confirmDelete(`/admin/properties/${property.id}/rates/${r.id}`)}>
                        Remove
                      </button>
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
            <label className="form-label">OT Pay ($)</label>
            <input className="form-input" value={data.ot_pay_rate} onChange={(e) => setData('ot_pay_rate', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">OT Bill ($)</label>
            <input className="form-input" value={data.ot_bill_rate} onChange={(e) => setData('ot_bill_rate', e.target.value)} required />
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
            <tr className="bg-light/25 text-2xs uppercase">
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
                  <td className="text-end space-x-3">
                    {can.downloadContracts && (
                      <a
                        href={`/admin/properties/${property.id}/contracts/${c.id}/download`}
                        className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
                        title="Download contract"
                      >
                        <Icon icon="eye" className="text-base" />
                      </a>
                    )}
                    {can.editContracts && (
                      <button className="text-danger text-sm hover:underline" onClick={() => confirmDelete(`/admin/properties/${property.id}/contracts/${c.id}`)}>
                        Delete
                      </button>
                    )}
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
            <tr className="bg-light/25 text-2xs uppercase">
              <th>Person</th>
              <th>Role</th>
              {can.manageAssignments && <th></th>}
            </tr>
          </thead>
          <tbody>
            {assignments.length ? (
              assignments.map((a) => (
                <tr key={a.id}>
                  <td className="font-medium">{a.person}</td>
                  <td>{a.role_label}</td>
                  {can.manageAssignments && (
                    <td className="text-end">
                      <button className="text-danger text-sm hover:underline" onClick={() => confirmDelete(`/admin/properties/${property.id}/assignments/${a.id}`)}>
                        Remove
                      </button>
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
    </div>
  )
}

const HistoryTab = ({ history }: { history: HistoryRow[] }) => (
  <div className="table-wrapper">
    <table className="table table-hover">
      <thead className="thead-sm">
        <tr className="bg-light/25 text-2xs uppercase">
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

const Page = ({ property, departments, rates, assignments, contracts, history, catalogs, can }: Props) => {
  const tabs = [
    { key: 'profile', label: 'Profile', show: true },
    { key: 'departments', label: 'Departments', show: true },
    { key: 'rates', label: 'Positions & Rates', show: true },
    { key: 'contracts', label: 'Contracts', show: can.viewContracts },
    { key: 'team', label: 'Team', show: true },
    { key: 'history', label: 'History', show: true },
  ].filter((t) => t.show)

  // Active tab lives in the URL hash (#rates, #team, …) so tabs are
  // deep-linkable and survive refresh. Invalid/missing hash → first tab.
  const tabFromHash = () => {
    const hash = typeof window === 'undefined' ? '' : window.location.hash.slice(1)
    return tabs.some((t) => t.key === hash) ? hash : 'profile'
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

      <div className="card">
        <div className="card-header">
          <div className="flex items-center gap-3">
            <h4 className="card-title">{property.name}</h4>
            <span className={`badge badge-label ${property.status === 'active' ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary'} capitalize`}>{property.status}</span>
          </div>
          <div className="flex items-center gap-4">
            <Link href={`/admin/properties/${property.id}/grid`} className="btn bg-primary hover:bg-primary-hover px-4 py-1.5 font-semibold text-white">
              Weekly Timesheet
            </Link>
            <Link href={`/admin/properties/${property.id}/qr`} className="btn btn-light px-4 py-1.5 font-semibold">
              Clock-In QR
            </Link>
            <Link href="/admin/properties" className="text-default-500 text-sm hover:underline">
              ← All Properties
            </Link>
          </div>
        </div>

        <nav className="border-default-300 flex flex-wrap border-b px-4" aria-label="Tabs" role="tablist">
          {tabs.map((t) => (
            <button
              key={t.key}
              type="button"
              role="tab"
              aria-selected={active === t.key}
              onClick={() => selectTab(t.key)}
              className={`hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden ${
                active === t.key ? 'border-primary text-primary border-b' : ''
              }`}>
              {t.label}
            </button>
          ))}
        </nav>

        <div className="card-body p-6">
          {active === 'profile' && <ProfileTab property={property} can={can} />}
          {active === 'departments' && <DepartmentsTab property={property} departments={departments} catalogs={catalogs} can={can} />}
          {active === 'rates' && <RatesTab property={property} rates={rates} catalogs={catalogs} can={can} />}
          {active === 'contracts' && can.viewContracts && <ContractsTab property={property} contracts={contracts} catalogs={catalogs} can={can} />}
          {active === 'team' && <TeamTab property={property} assignments={assignments} catalogs={catalogs} can={can} />}
          {active === 'history' && <HistoryTab history={history} />}
        </div>
      </div>
    </>
  )
}

export default Page
