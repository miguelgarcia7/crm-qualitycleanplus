import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { useState } from 'react'

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
    emergency_contact: string
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
  submitted: 'badge badge-soft-info',
  reviewing: 'badge badge-soft-warning',
  promoted: 'badge badge-soft-success',
  rejected: 'badge badge-soft-danger',
}

const Page = ({ application: app, person, other_applications, checklist, checklist_complete, can }: Props) => {
  const [showReject, setShowReject] = useState(false)
  const rejectForm = useForm({ reason: '' })

  const startReview = () => router.post(`/admin/applicants/${app.id}/start-review`, {}, { preserveScroll: true })
  const submitReject = (e: React.FormEvent) => {
    e.preventDefault()
    rejectForm.post(`/admin/applicants/${app.id}/reject`, { preserveScroll: true, onSuccess: () => setShowReject(false) })
  }

  const isPending = app.status === 'submitted' || app.status === 'reviewing'

  return (
    <>
      <Head title={`Applicant — ${person.name}`} />
      <PageBreadcrumb title={person.name} subtitle="Applicants" />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Link href="/admin/applicants" className="btn btn-sm btn-light">
          ← Queue
        </Link>
        <span className={statusBadge[app.status] ?? 'badge'}>{app.status_label}</span>
        <span className="text-muted text-sm">Submitted {app.submitted_at}</span>
        {app.reviewed_by && (
          <span className="text-muted text-sm">
            · Reviewer: {app.reviewed_by} {app.reviewed_at && `(${app.reviewed_at})`}
          </span>
        )}
        <span className="grow" />
        {can.review && app.status === 'submitted' && (
          <button className="btn btn-sm bg-primary text-white" onClick={startReview}>
            Start review
          </button>
        )}
        {can.review && isPending && (
          <button className="btn btn-sm btn-light text-danger" onClick={() => setShowReject((v) => !v)}>
            Reject…
          </button>
        )}
      </div>

      {showReject && (
        <form onSubmit={submitReject} className="card mb-4 rounded-2xl">
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
        <div className="card mb-4 rounded-2xl">
          <div className="card-body p-4">
            <span className="text-danger font-medium">Rejected:</span> {app.rejected_reason}
          </div>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="space-y-4">
          <div className="card rounded-2xl">
            <div className="card-body p-5">
              <h4 className="card-title mb-3">Application</h4>
              <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt className="text-muted">Posting</dt>
                <dd>{app.posting ?? '— (open application)'}</dd>
                <dt className="text-muted">Desired position</dt>
                <dd>{app.desired_position ?? '—'}</dd>
                <dt className="text-muted">Desired salary</dt>
                <dd>{app.desired_salary ?? '—'}</dd>
                <dt className="text-muted">Can start</dt>
                <dd>{app.desired_start_date ?? '—'}</dd>
                <dt className="text-muted">Reliable transportation</dt>
                <dd>{yesNo(app.transportation)}</dd>
                <dt className="text-muted">Worked at QCP (6 mo)</dt>
                <dd>
                  {yesNo(app.work_at_qcp)}
                  {app.work_at_qcp_explain && <span className="text-muted"> — {app.work_at_qcp_explain}</span>}
                </dd>
                <dt className="text-muted">Another staffing agency</dt>
                <dd>
                  {yesNo(app.another_staff_agency)}
                  {app.non_complete && <span className="text-muted"> — non-compete: {app.non_complete}</span>}
                </dd>
                <dt className="text-muted">Felony conviction</dt>
                <dd>
                  {yesNo(app.convicted_felon)}
                  {app.felony_conviction && <span className="text-muted"> — {app.felony_conviction}</span>}
                </dd>
                <dt className="text-muted">Certified true &amp; correct</dt>
                <dd>{app.acknowledgement ? 'Yes' : 'No'}</dd>
              </dl>
            </div>
          </div>

          <div className="card rounded-2xl">
            <div className="card-body p-5">
              <h4 className="card-title mb-3">Applicant</h4>
              <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt className="text-muted">Email</dt>
                <dd>{person.email ?? '—'}</dd>
                <dt className="text-muted">Phone</dt>
                <dd>{person.phone ?? '—'}</dd>
                <dt className="text-muted">Date of birth</dt>
                <dd>{person.dob ?? '—'}</dd>
                <dt className="text-muted">Address</dt>
                <dd>{person.address || '—'}</dd>
                <dt className="text-muted">US citizen</dt>
                <dd>{yesNo(person.usa_citizen)}</dd>
                <dt className="text-muted">Eligible to work</dt>
                <dd>{yesNo(person.eligible_to_work)}</dd>
                <dt className="text-muted">Emergency contact</dt>
                <dd>{person.emergency_contact || '—'}</dd>
                <dt className="text-muted">First applied</dt>
                <dd>{person.application_date ?? '—'}</dd>
              </dl>
            </div>
          </div>

          {other_applications.length > 0 && (
            <div className="card rounded-2xl">
              <div className="card-body p-5">
                <h4 className="card-title mb-3">Other applications</h4>
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

        <div className="space-y-4">
          <div className="card rounded-2xl">
            <div className="card-body p-5">
              <div className="mb-3 flex items-center justify-between">
                <h4 className="card-title">Onboarding checklist</h4>
                {checklist_complete ? (
                  <span className="badge badge-soft-success">Complete</span>
                ) : (
                  <span className="badge badge-soft-warning">Incomplete</span>
                )}
              </div>
              <ul className="divide-default-100 divide-y text-sm">
                {checklist.map((item) => (
                  <li key={item.key} className="flex items-center gap-3 py-2">
                    <span className={`size-2.5 shrink-0 rounded-full ${item.complete ? 'bg-success' : item.waived ? 'bg-warning' : 'bg-default-300'}`} />
                    <span className="grow">
                      {item.label}
                      {item.needs_verification && item.file_id !== null && (
                        <span className={`ms-2 text-xs ${item.verified ? 'text-success' : 'text-warning'}`}>
                          {item.verified ? 'verified' : 'awaiting verification'}
                        </span>
                      )}
                      {item.waived && <span className="text-warning ms-2 text-xs">waived</span>}
                    </span>
                    <span className="text-muted text-xs">{item.completed_at ?? ''}</span>
                  </li>
                ))}
              </ul>
              <p className="text-default-400 mt-3 text-xs">
                Document uploads, I-9 verification, and promotion arrive on this page in the next increment.
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
