import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { useRef, useState } from 'react'

type ChecklistItem = {
  key: string
  label: string
  complete: boolean
  waived: boolean
  completed_at: string | null
  file_id: number | null
  needs_verification: boolean
  verified: boolean
}

type Props = {
  application: {
    id: number
    status: string
    status_label: string
    submitted_at: string
    posting: string | null
    desired_position: string | null
    desired_salary: string | null
    desired_start_date: string | null
    transportation: boolean | null
    work_at_qcp: boolean | null
    work_at_qcp_explain: string | null
    another_staff_agency: boolean | null
    non_complete: string | null
    convicted_felon: boolean | null
    felony_conviction: string | null
    acknowledgement: boolean
    reviewed_by: string | null
    reviewed_at: string | null
    rejected_reason: string | null
    promoted_at: string | null
  }
  person: {
    id: number
    name: string
    status: string
    email: string | null
    phone: string | null
    dob: string | null
    address: string
    usa_citizen: boolean | null
    eligible_to_work: boolean | null
    emergency_contact_name: string | null
    emergency_contact_phone: string | null
    emergency_contact_relationship: string | null
    emergency_contact_address: string | null
    application_date: string | null
    has_work_orders: boolean
  }
  other_applications: { id: number; status_label: string; desired_position: string | null; submitted_at: string }[]
  checklist: ChecklistItem[]
  checklist_complete: boolean
  can: { review: boolean; edit_checklist: boolean; waive: boolean; promote: boolean; reverse: boolean }
}

const yesNo = (v: boolean | null) => (v === null ? '—' : v ? 'Yes' : 'No')

const statusBadge: Record<string, string> = {
  submitted: 'bg-info/15 text-info',
  reviewing: 'bg-warning/15 text-warning',
  promoted: 'bg-success/15 text-success',
  rejected: 'bg-danger/15 text-danger',
}

/** Label/value cell for the 3-column info grids (HRM staff-profile pattern). */
const Field = ({ label, children }: { label: string; children: React.ReactNode }) => (
  <div>
    <p className="text-default-400 mb-1.25 font-medium">{label}</p>
    <p>{children}</p>
  </div>
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

const ChecklistRow = ({ item, appId, can }: { item: ChecklistItem; appId: number; can: Props['can'] }) => {
  const fileInput = useRef<HTMLInputElement>(null)
  const isDocument = item.key !== 'background_check'

  const upload = (file: File | undefined) => {
    if (!file) return
    router.post(`/admin/applicants/${appId}/onboarding/${item.key}`, { document: file }, { forceFormData: true, preserveScroll: true })
  }
  const verify = () => router.post(`/admin/applicants/${appId}/onboarding/i9/verify`, {}, { preserveScroll: true })
  const toggleWaive = () =>
    router.post(`/admin/applicants/${appId}/onboarding/${item.key}/waive`, { waived: !item.waived }, { preserveScroll: true })

  return (
    <li className="flex flex-wrap items-center gap-2 py-2.5">
      <span className={`size-2.5 shrink-0 rounded-full ${item.complete ? 'bg-success' : item.waived ? 'bg-warning' : 'bg-default-300'}`} />
      <span className="grow text-sm">
        {item.label}
        {item.needs_verification && item.file_id !== null && (
          <span className={`ms-2 text-xs ${item.verified ? 'text-success' : 'text-warning'}`}>
            {item.verified ? 'verified' : 'awaiting verification'}
          </span>
        )}
        {item.waived && <span className="text-warning ms-2 text-xs">waived</span>}
        {item.completed_at && <div className="text-default-400 text-xs">{item.completed_at}</div>}
      </span>

      <span className="flex shrink-0 items-center gap-1.5">
        {isDocument && item.file_id !== null && (
          <a
            className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
            href={`/admin/applicants/${appId}/onboarding/${item.key}/download`}
            title="View document"
          >
            <Icon icon="eye" className="text-base" />
          </a>
        )}
        {isDocument && can.edit_checklist && (
          <>
            <input
              ref={fileInput}
              type="file"
              className="hidden"
              accept=".pdf,.jpg,.jpeg,.png,.webp,.heic"
              onChange={(e) => upload(e.target.files?.[0])}
            />
            <button
              className="btn btn-icon btn-sm border-default-300 hover:border-default-400 border"
              onClick={() => fileInput.current?.click()}
              title={item.file_id ? 'Replace document' : 'Upload document'}
            >
              <Icon icon="cloud-upload" className="text-base" />
            </button>
          </>
        )}
        {item.key === 'i9' && can.edit_checklist && item.file_id !== null && !item.verified && (
          <button className="btn btn-sm btn-soft-success" onClick={verify}>
            Verify
          </button>
        )}
        {can.waive && !item.complete && (
          <button className="btn btn-sm btn-soft-warning" onClick={toggleWaive}>
            {item.waived ? 'Unwaive' : 'Waive'}
          </button>
        )}
      </span>
    </li>
  )
}

const Page = ({ application: app, person, other_applications, checklist, checklist_complete, can }: Props) => {
  const { errors } = usePage().props as { errors: Record<string, string> }
  const [showReject, setShowReject] = useState(false)
  const rejectForm = useForm({ reason: '' })

  const startReview = () => router.post(`/admin/applicants/${app.id}/start-review`, {}, { preserveScroll: true })
  const submitReject = (e: React.FormEvent) => {
    e.preventDefault()
    rejectForm.post(`/admin/applicants/${app.id}/reject`, { preserveScroll: true, onSuccess: () => setShowReject(false) })
  }
  const promote = () => {
    if (confirm(`Promote ${person.name} to active contractor?`)) {
      router.post(`/admin/applicants/${app.id}/promote`, {}, { preserveScroll: true })
    }
  }
  const reverse = () => {
    if (confirm('Reverse this promotion? Status returns to applicant.')) {
      router.post(`/admin/applicants/${app.id}/reverse`, {}, { preserveScroll: true })
    }
  }

  const isPending = app.status === 'submitted' || app.status === 'reviewing'
  const initials = person.name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  return (
    <>
      <Head title={`Applicant — ${person.name}`} />
      <PageBreadcrumb title={person.name} subtitle="Applicants" />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Link href="/admin/applicants" className="btn btn-sm btn-light">
          ← Queue
        </Link>
        <span className="grow" />
        {can.review && app.status === 'submitted' && (
          <button className="btn btn-sm bg-primary text-white" onClick={startReview}>
            Start review
          </button>
        )}
        {can.promote && isPending && (
          <button
            className="btn btn-sm bg-success text-white"
            onClick={promote}
            disabled={!checklist_complete}
            title={checklist_complete ? '' : 'Complete or waive all checklist items first'}
          >
            Promote to contractor
          </button>
        )}
        {can.reverse && app.status === 'promoted' && !person.has_work_orders && (
          <button className="btn btn-sm btn-soft-danger" onClick={reverse}>
            Reverse promotion
          </button>
        )}
        {can.review && isPending && (
          <button className="btn btn-sm btn-soft-danger" onClick={() => setShowReject((v) => !v)}>
            Reject…
          </button>
        )}
      </div>

      {Object.keys(errors).length > 0 && (
        <div className="card border-danger mb-4 border">
          <div className="card-body text-danger p-4 text-sm">
            {Object.values(errors).map((message, i) => (
              <p key={i}>{message}</p>
            ))}
          </div>
        </div>
      )}

      {app.status === 'promoted' && (
        <div className="card mb-4">
          <div className="card-body p-4 text-sm">
            <span className="text-success font-medium">Promoted</span> {app.promoted_at && `on ${app.promoted_at}`}
            {person.has_work_orders ? ' — has work orders, so the promotion is permanent.' : ' — reversible until work orders are created.'}
          </div>
        </div>
      )}

      {showReject && (
        <form onSubmit={submitReject} className="card mb-4">
          <div className="card-body flex flex-wrap items-end gap-3 p-4">
            <div className="grow">
              <label className="form-label">Rejection reason</label>
              <input
                className="form-input w-full"
                value={rejectForm.data.reason}
                onChange={(e) => rejectForm.setData('reason', e.target.value)}
                required
              />
              {rejectForm.errors.reason && <p className="text-danger text-sm">{rejectForm.errors.reason}</p>}
            </div>
            <button className="btn bg-danger text-white" disabled={rejectForm.processing}>
              Reject application
            </button>
          </div>
        </form>
      )}

      {app.rejected_reason && (
        <div className="card mb-4">
          <div className="card-body p-4">
            <span className="text-danger font-medium">Rejected:</span> {app.rejected_reason}
          </div>
        </div>
      )}

      <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
        <div className="space-y-6">
          <div className="card">
            <div className="card-body">
              <div className="mb-7.5 flex items-center justify-between">
                <div className="gap-base flex items-center">
                  <div className="bg-primary/10 text-primary flex size-18 shrink-0 items-center justify-center rounded-full text-xl font-semibold">
                    {initials}
                  </div>
                  <div>
                    <h5 className="font-medium">{person.name}</h5>
                    <p className="text-default-400 mb-3">{app.desired_position ?? 'Open application'}</p>
                    <span className={cn('badge badge-label', statusBadge[app.status] ?? 'bg-light text-dark')}>{app.status_label}</span>
                  </div>
                </div>
              </div>
              <div className="flex flex-col gap-y-3">
                {person.email && (
                  <FactRow icon="mail" label="Email">
                    <a href={`mailto:${person.email}`} className="text-primary font-semibold">
                      {person.email}
                    </a>
                  </FactRow>
                )}
                {person.phone && (
                  <FactRow icon="phone" label="Phone">
                    {person.phone}
                  </FactRow>
                )}
                {person.address && (
                  <FactRow icon="map-pin" label="Lives at">
                    {person.address}
                  </FactRow>
                )}
                {person.dob && (
                  <FactRow icon="calendar" label="Born">
                    {person.dob}
                  </FactRow>
                )}
                <FactRow icon="files" label="Applied via">
                  {app.posting ?? 'Open application'}
                </FactRow>
                <FactRow icon="clock" label="Submitted">
                  {app.submitted_at}
                </FactRow>
                {app.reviewed_by && (
                  <FactRow icon="user-circle" label="Reviewer">
                    {app.reviewed_by}
                  </FactRow>
                )}
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Onboarding Checklist</h4>
              {checklist_complete ? (
                <span className="badge badge-label bg-success/15 text-success">Complete</span>
              ) : (
                <span className="badge badge-label bg-warning/15 text-warning">Incomplete</span>
              )}
            </div>
            <div className="card-body">
              <ul className="divide-default-200 divide-y">
                {checklist.map((item) => (
                  <ChecklistRow key={item.key} item={item} appId={app.id} can={can} />
                ))}
              </ul>

              {can.edit_checklist && (
                <div className="border-default-200 mt-3 border-t pt-3">
                  <label className="form-label">Background check status</label>
                  <select
                    className="form-select w-full"
                    defaultValue={''}
                    onChange={(e) =>
                      e.target.value &&
                      router.post(`/admin/applicants/${app.id}/background-check`, { status: e.target.value }, { preserveScroll: true })
                    }
                  >
                    <option value="">Set status…</option>
                    <option value="not_required">Not required</option>
                    <option value="pending">Pending</option>
                    <option value="passed">Passed</option>
                    <option value="failed">Failed</option>
                  </select>
                </div>
              )}

              <p className="text-default-400 mt-3 text-xs">
                Promotion requires every item complete or waived (waiving is an HR/admin power). Uniform issuance is tracked through Inventory.
              </p>
            </div>
          </div>
        </div>

        <div className="space-y-6 xl:col-span-2">
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Application Details</h4>
            </div>
            <div className="card-body">
              <div className="gap-x-base grid grid-cols-1 gap-y-5 md:grid-cols-3">
                <Field label="Posting">{app.posting ?? '— (open application)'}</Field>
                <Field label="Desired position">{app.desired_position ?? '—'}</Field>
                <Field label="Desired salary">{app.desired_salary ?? '—'}</Field>
                <Field label="Can start">{app.desired_start_date ?? '—'}</Field>
                <Field label="Submitted">{app.submitted_at}</Field>
                <Field label="Reviewer">{app.reviewed_by ? `${app.reviewed_by}${app.reviewed_at ? ` (${app.reviewed_at})` : ''}` : '—'}</Field>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Declarations</h4>
            </div>
            <div className="card-body">
              <div className="gap-x-base grid grid-cols-1 gap-y-5 md:grid-cols-3">
                <Field label="Reliable transportation">{yesNo(app.transportation)}</Field>
                <Field label="Worked at QCP (last 6 months)">
                  {yesNo(app.work_at_qcp)}
                  {app.work_at_qcp_explain && <span className="text-default-400"> — {app.work_at_qcp_explain}</span>}
                </Field>
                <Field label="Another staffing agency">
                  {yesNo(app.another_staff_agency)}
                  {app.non_complete && <span className="text-default-400"> — non-compete: {app.non_complete}</span>}
                </Field>
                <Field label="Felony conviction">
                  {yesNo(app.convicted_felon)}
                  {app.felony_conviction && <span className="text-default-400"> — {app.felony_conviction}</span>}
                </Field>
                <Field label="Certified true & correct">{app.acknowledgement ? 'Yes' : 'No'}</Field>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Basic Information</h4>
            </div>
            <div className="card-body">
              <div className="gap-x-base grid grid-cols-1 gap-y-5 md:grid-cols-3">
                <Field label="Phone">{person.phone ?? '—'}</Field>
                <Field label="Email">{person.email ?? '—'}</Field>
                <Field label="Birthday">{person.dob ?? '—'}</Field>
                <Field label="Address">{person.address || '—'}</Field>
                <Field label="US citizen">{yesNo(person.usa_citizen)}</Field>
                <Field label="Eligible to work">{yesNo(person.eligible_to_work)}</Field>
                <Field label="First applied">{person.application_date ?? '—'}</Field>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Emergency Contact Details</h4>
            </div>
            <div className="card-body">
              <div className="gap-x-base grid grid-cols-1 gap-y-5 md:grid-cols-3">
                <Field label="Contact Name">{person.emergency_contact_name ?? '—'}</Field>
                <Field label="Relationship">{person.emergency_contact_relationship ?? '—'}</Field>
                <Field label="Phone">{person.emergency_contact_phone ?? '—'}</Field>
                <Field label="Address">{person.emergency_contact_address ?? '—'}</Field>
              </div>
            </div>
          </div>

          {other_applications.length > 0 && (
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Other Applications</h4>
              </div>
              <div className="card-body">
                <ul className="space-y-1 text-sm">
                  {other_applications.map((a) => (
                    <li key={a.id}>
                      <Link href={`/admin/applicants/${a.id}`} className="text-primary">
                        {a.desired_position ?? 'Open application'}
                      </Link>{' '}
                      — {a.status_label} · {a.submitted_at}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  )
}

export default Page
