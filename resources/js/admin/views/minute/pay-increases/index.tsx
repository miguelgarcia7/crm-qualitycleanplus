import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Req = {
  id: number
  status: string
  contractor: string | null
  position: string | null
  property: string | null
  increase: number
  current_bill_rate: number | null
  approved_bill_rate: number | null
  effective: string | null
  decision_note: string | null
  reason: string | null
  created_at: string | null
  can_change: boolean
}
type WoOption = { id: number; label: string; bill_rate: number }
type Props = { requests: Req[]; workOrders: WoOption[]; can: { initiate: boolean } }

const money = (c: number) => `$${(c / 100).toFixed(2)}`

const STATUS: Record<string, { label: string; className: string }> = {
  in_progress: { label: 'Waiting for approval', className: 'bg-warning/15 text-warning' },
  completed: { label: 'Approved', className: 'bg-success/15 text-success' },
  rejected: { label: 'Declined', className: 'bg-danger/15 text-danger' },
  cancelled: { label: 'Cancelled', className: 'bg-secondary/15 text-secondary' },
}

const Page = ({ requests, workOrders, can }: Props) => {
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Req | null>(null)

  const cancel = (r: Req) =>
    confirmAction({
      title: 'Cancel pay increase request',
      message: (
        <>
          Cancel the <strong>+{money(r.increase)}/hr</strong> request for <strong>{r.contractor}</strong>? The recruiter will no longer see it.
        </>
      ),
      confirmLabel: 'Cancel request',
      cancelLabel: 'Keep it',
      onConfirm: () => router.post(`/pay-increases/${r.id}/cancel`, {}, { preserveScroll: true }),
    })

  return (
    <>
      <Head title="Pay Increases" />
      <PageBreadcrumb title="Pay Increases" subtitle="QC Minute" />

      <div className="card">
        <div className="card-header">
          <h4 className="card-title">My requests</h4>
          {can.initiate && workOrders.length > 0 && (
            <button className="btn bg-primary hover:bg-primary-hover px-4 py-1.5 font-semibold text-white" onClick={() => setCreating(true)}>+ Request pay increase</button>
          )}
        </div>
        <div className="table-wrapper">
          <table className="table table-hover text-sm">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Contractor</th>
                <th>Requested</th>
                <th className="text-end">Rate</th>
                <th>Reason</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {requests.length ? (
                requests.map((r) => {
                  const status = STATUS[r.status] ?? { label: r.status, className: 'bg-secondary/15 text-secondary' }
                  const asked = r.current_bill_rate !== null ? r.current_bill_rate + r.increase : null
                  return (
                    <tr key={r.id}>
                      <td>
                        <span className="font-medium">{r.contractor ?? '—'}</span>
                        <p className="text-default-400 text-xs">
                          {r.position}
                          {r.property ? ` · ${r.property}` : ''}
                        </p>
                      </td>
                      <td className="text-nowrap">{r.created_at}</td>
                      <td className="text-end text-nowrap">
                        <span className="font-medium">+{money(r.increase)}/hr</span>
                        {r.current_bill_rate !== null && asked !== null && (
                          <p className="text-default-400 text-xs">
                            {money(r.current_bill_rate)} → {money(r.approved_bill_rate ?? asked)}
                          </p>
                        )}
                      </td>
                      <td className="text-default-400">{r.reason}</td>
                      <td>
                        <span className={`badge badge-label ${status.className}`}>{status.label}</span>
                        {r.status === 'completed' && r.effective && <p className="text-default-400 mt-1 text-xs">From {r.effective}</p>}
                        {r.decision_note && <p className="text-default-400 mt-1 text-xs">{r.decision_note}</p>}
                      </td>
                      <td className="text-end text-nowrap">
                        {r.can_change && (
                          <div className="flex justify-end gap-3">
                            <button className="text-primary text-sm hover:underline" onClick={() => setEditing(r)}>Edit</button>
                            <button className="text-danger text-sm hover:underline" onClick={() => cancel(r)}>Cancel</button>
                          </div>
                        )}
                      </td>
                    </tr>
                  )
                })
              ) : (
                <tr><td colSpan={6} className="text-default-400 py-4 text-center">No requests yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {creating && <CreateModal workOrders={workOrders} onClose={() => setCreating(false)} />}
      {editing && <EditModal request={editing} onClose={() => setEditing(null)} />}
    </>
  )
}

/** Change a waiting request's amount or reason; the contractor stays the same. */
const EditModal = ({ request, onClose }: { request: Req; onClose: () => void }) => {
  const { data, setData, patch, processing, errors } = useForm({
    increase_amount: (request.increase / 100).toFixed(2),
    reason: request.reason ?? '',
  })
  const increaseCents = Math.round(Number(data.increase_amount) * 100)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch(`/pay-increases/${request.id}`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <div>
            <h4 className="card-title">Edit request — {request.contractor}</h4>
            <p className="text-default-400 text-sm">{request.position} · {request.property}</p>
          </div>
        </div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            {request.current_bill_rate !== null && (
              <p className="text-default-400 text-sm">
                Your current rate: <span className="text-body-color font-semibold">{money(request.current_bill_rate)} / hr</span>
              </p>
            )}
            <div>
              <label className="form-label">Increase per hour ($)</label>
              <input type="number" step="0.01" min="0.01" className="form-input" value={data.increase_amount} onChange={(e) => setData('increase_amount', e.target.value)} required />
              {errors.increase_amount && <p className="text-danger mt-1 text-sm">{errors.increase_amount}</p>}
              {request.current_bill_rate !== null && increaseCents > 0 && (
                <p className="mt-2 text-sm">
                  Your new rate will be: <span className="font-semibold">{money(request.current_bill_rate + increaseCents)} / hr</span>
                </p>
              )}
            </div>
            <div>
              <label className="form-label">Reason</label>
              <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
              {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
            </div>
            {'request' in errors && <p className="text-danger text-sm">{(errors as Record<string, string>).request}</p>}
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Close</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Save changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

const CreateModal = ({ workOrders, onClose }: { workOrders: WoOption[]; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<{
    work_order_id: number | string; increase_amount: string; reason: string
  }>({
    work_order_id: workOrders[0]?.id ?? ('' as number | string),
    increase_amount: '',
    reason: '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/pay-increases', { preserveScroll: true, onSuccess: onClose })
  }

  // The hotel's side only: what it pays QCP per hour now, and after the increase.
  const selected = workOrders.find((w) => String(w.id) === String(data.work_order_id))
  const increaseCents = Math.round(Number(data.increase_amount) * 100)
  const newBillRate = selected && increaseCents > 0 ? selected.bill_rate + increaseCents : null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">Request pay increase</h4></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Contractor</label>
              <select className="form-select" value={data.work_order_id} onChange={(e) => setData('work_order_id', e.target.value)} required>
                {workOrders.map((w) => <option key={w.id} value={w.id}>{w.label}</option>)}
              </select>
              {selected && (
                <p className="text-default-400 mt-2 text-sm">
                  Your current rate:{' '}
                  <span className="text-body-color font-semibold">{money(selected.bill_rate)} / hr</span>
                </p>
              )}
            </div>
            <div>
              <label className="form-label">Increase per hour ($)</label>
              <input type="number" step="0.01" min="0.01" className="form-input" value={data.increase_amount} onChange={(e) => setData('increase_amount', e.target.value)} required />
              {errors.increase_amount && <p className="text-danger mt-1 text-sm">{errors.increase_amount}</p>}
              {newBillRate !== null && (
                <p className="mt-2 text-sm">
                  Your new rate will be: <span className="font-semibold">{money(newBillRate)} / hr</span>
                </p>
              )}
              <p className="text-default-400 mt-1 text-xs">A recruiter reviews and sets the final rates.</p>
            </div>
            <div>
              <label className="form-label">Reason</label>
              <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
              {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Submit</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
