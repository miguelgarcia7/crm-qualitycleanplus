import PageBreadcrumb from '@/components/PageBreadcrumb'
import SearchMultiSelect from '@/components/SearchMultiSelect'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

type Option = { id: number; name: string }
type RoleOption = { value: string; label: string }
type Props = {
  roles: RoleOption[]
  properties: Option[]
  preselectedPropertyId: number | null
}

const today = () => new Date().toISOString().slice(0, 10)

const Page = ({ roles, properties, preselectedPropertyId }: Props) => {
  const { data, setData, post, processing, errors, transform } = useForm<{
    role: string
    name: string
    email: string
    phone: string
    hire_date: string
    property_ids: number[]
  }>({
    role: preselectedPropertyId ? 'property_manager' : '',
    name: '',
    email: '',
    phone: '',
    hire_date: today(),
    property_ids: preselectedPropertyId ? [preselectedPropertyId] : [],
  })

  const isPm = data.role === 'property_manager'
  const isRecruiter = data.role === 'recruiter'
  const showProperties = isPm || isRecruiter
  const isStaff = data.role !== '' && !isPm

  const setRole = (role: string) => {
    setData((d) => ({
      ...d,
      role,
      // Property list only travels with roles that take assignments.
      property_ids: role === 'property_manager' || role === 'recruiter' ? d.property_ids : [],
    }))
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    // Only send the fields this role uses; the server prohibits the rest.
    transform((d) => ({
      role: d.role,
      name: d.name,
      email: d.email,
      phone: d.phone,
      ...(isStaff ? { hire_date: d.hire_date } : {}),
      ...(showProperties ? { property_ids: d.property_ids } : {}),
    }))
    post('/admin/people/invite')
  }

  return (
    <>
      <Head title="Invite User" />
      <PageBreadcrumb title="Invite User" subtitle="People" />

      <div className="card max-w-2xl rounded-2xl">
        <div className="card-body p-6">
          <p className="text-default-500 mb-6 text-sm">
            Creates a login and emails an invitation with a link to set their own password. Property managers sign in on QC Minute to review
            timesheets and invoices for their assigned properties; staff roles sign in on the back office.
          </p>

          <form onSubmit={submit} className="space-y-5">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label className="form-label">Role</label>
                <select className="form-select" value={data.role} onChange={(e) => setRole(e.target.value)} required>
                  <option value="">Select…</option>
                  {roles.map((r) => (
                    <option key={r.value} value={r.value}>
                      {r.label}
                    </option>
                  ))}
                </select>
                {errors.role && <p className="text-danger mt-1 text-sm">{errors.role}</p>}
              </div>
              <div>
                <label className="form-label">Full name</label>
                <input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                {errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}
              </div>
              <div>
                <label className="form-label">Email</label>
                <input
                  type="email"
                  className="form-input"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  required
                />
                {errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}
              </div>
              <div>
                <label className="form-label">Phone (optional)</label>
                <input className="form-input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                {errors.phone && <p className="text-danger mt-1 text-sm">{errors.phone}</p>}
              </div>
              {isStaff && (
                <div>
                  <label className="form-label">Hire date</label>
                  <input
                    type="date"
                    className="form-input"
                    value={data.hire_date}
                    onChange={(e) => setData('hire_date', e.target.value)}
                    required
                  />
                  <p className="text-default-400 mt-1 text-xs">PTO accrual tiers and anniversaries are based on this date.</p>
                  {errors.hire_date && <p className="text-danger mt-1 text-sm">{errors.hire_date}</p>}
                </div>
              )}
            </div>

            {showProperties && (
              <div>
                <label className="form-label">{isPm ? 'Properties they manage' : 'Assign properties (optional)'}</label>
                <SearchMultiSelect
                  options={properties}
                  selected={data.property_ids}
                  onChange={(ids) => setData('property_ids', ids)}
                  placeholder="Search properties…"
                  emptyText="No matching properties."
                />
                {isRecruiter && (
                  <p className="text-default-400 mt-1 text-xs">Their starting book of properties — can be changed later on each property&apos;s Access tab.</p>
                )}
                {errors.property_ids && <p className="text-danger mt-1 text-sm">{errors.property_ids}</p>}
              </div>
            )}

            <div className="flex items-center gap-3">
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white" disabled={processing}>
                Send Invitation
              </button>
              <Link href="/admin/people" className="btn btn-light px-6 py-2.5">
                Cancel
              </Link>
            </div>
          </form>
        </div>
      </div>
    </>
  )
}

export default Page
