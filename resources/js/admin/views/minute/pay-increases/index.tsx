import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Req = { id: number; status: string; increase: number; reason: string | null; created_at: string | null }
type WoOption = { id: number; label: string }
type Props = { requests: Req[]; workOrders: WoOption[]; can: { initiate: boolean } }

const money = (c: number) => `$${(c / 100).toFixed(2)}`
const statusClass = (s: string) =>
  s === 'completed' ? 'bg-success/15 text-success' : s === 'rejected' ? 'bg-danger/15 text-danger' : 'bg-secondary/15 text-secondary'

const Page = ({ requests, workOrders, can }: Props) => {
  const [creating, setCreating] = useState(false)

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
              <tr className="bg-light/25 text-2xs uppercase"><th>Requested</th><th className="text-end">Increase</th><th>Reason</th><th>Status</th></tr>
            </thead>
            <tbody>
              {requests.length ? requests.map((r) => (
                <tr key={r.id}>
                  <td>{r.created_at}</td>
                  <td className="text-end">{money(r.increase)}/hr</td>
                  <td className="text-default-400">{r.reason}</td>
                  <td><span className={`badge badge-label ${statusClass(r.status)} capitalize`}>{r.status.replaceAll('_', ' ')}</span></td>
                </tr>
              )) : <tr><td colSpan={4} className="text-default-400 py-4 text-center">No requests yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      {creating && <CreateModal workOrders={workOrders} onClose={() => setCreating(false)} />}
    </>
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
            </div>
            <div>
              <label className="form-label">Increase per hour ($)</label>
              <input type="number" step="0.01" min="0.01" className="form-input" value={data.increase_amount} onChange={(e) => setData('increase_amount', e.target.value)} required />
              {errors.increase_amount && <p className="text-danger mt-1 text-sm">{errors.increase_amount}</p>}
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
