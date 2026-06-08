import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router, useForm } from '@inertiajs/react'
import { FormEvent, useMemo, useState } from 'react'

type Position = { id: number; name: string }
type Property = { id: number; name: string; positions: Position[] }
type Option = { value: string; label: string }
type Req = {
  id: number; property: string; position: string; quantity_requested: number
  quantity_fulfilled: number; by_date: string; urgency: string; status: string
  status_label: string; is_overdue: boolean
}
type Props = { properties: Property[]; requests: Req[]; urgencies: Option[]; can: { initiate: boolean } }

const statusBadge = (s: string) =>
  s === 'fulfilled' ? 'badge-soft-success' : s === 'declined' || s === 'cancelled' ? 'badge-soft-danger' : 'badge-soft-primary'

const Page = ({ properties, requests, urgencies, can }: Props) => {
  const [create, setCreate] = useState(false)

  return (
    <>
      <Head title="Staffing Requests" />
      <PageBreadcrumb title="Staffing Requests" subtitle="QC Minute" />

      <div className="card rounded-2xl">
        <div className="card-header flex items-center justify-between p-6">
          <h4 className="card-title">My Requests</h4>
          {can.initiate && properties.length > 0 && (
            <button className="btn bg-primary px-4 py-1.5 font-semibold text-white" onClick={() => setCreate(true)}>+ Request Staff</button>
          )}
        </div>
        <div className="table-wrapper">
          <table className="table table-hover text-sm">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-2xs uppercase">
                <th>Property</th><th>Position</th><th className="text-center">Progress</th><th>By date</th><th>Status</th><th className="text-end"></th>
              </tr>
            </thead>
            <tbody>
              {requests.length ? requests.map((r) => (
                <tr key={r.id}>
                  <td className="font-medium">{r.property}</td>
                  <td>{r.position}</td>
                  <td className="text-center">{r.quantity_fulfilled} / {r.quantity_requested}</td>
                  <td>{r.by_date}{r.is_overdue && <span className="badge badge-soft-danger ms-2">Overdue</span>}</td>
                  <td><span className={`badge ${statusBadge(r.status)}`}>{r.status_label}</span></td>
                  <td className="text-end">
                    {(r.status === 'submitted' || r.status === 'in_progress') && (
                      <button
                        className="btn btn-sm btn-light"
                        onClick={() => { const reason = window.prompt('Reason for cancelling?'); if (reason) router.post(`/staffing-requests/${r.id}/cancel`, { reason }, { preserveScroll: true }) }}
                      >Cancel</button>
                    )}
                  </td>
                </tr>
              )) : <tr><td colSpan={6} className="text-default-400 py-4 text-center">No requests yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      {create && <CreateModal properties={properties} urgencies={urgencies} onClose={() => setCreate(false)} />}
    </>
  )
}

const CreateModal = ({ properties, urgencies, onClose }: { properties: Property[]; urgencies: Option[]; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<{
    property_id: number | string; position_id: number | string; quantity: number
    by_date: string; urgency: string; reason: string; notes: string
  }>({
    property_id: properties[0]?.id ?? '', position_id: '', quantity: 1,
    by_date: '', urgency: 'normal', reason: '', notes: '',
  })

  const positions = useMemo(
    () => properties.find((p) => p.id === Number(data.property_id))?.positions ?? [],
    [properties, data.property_id],
  )

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/staffing-requests', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-lg rounded-2xl" onClick={(e) => e.stopPropagation()}>
        <div className="card-header p-5"><h4 className="card-title">Request More Staff</h4></div>
        <div className="card-body max-h-[75vh] overflow-y-auto p-5">
          <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="form-label">Property</label>
                <select className="form-select" value={data.property_id} onChange={(e) => { setData('property_id', e.target.value); setData('position_id', '') }}>
                  {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
              </div>
              <div>
                <label className="form-label">Position</label>
                <select className="form-select" value={data.position_id} onChange={(e) => setData('position_id', e.target.value)} required>
                  <option value="">Select…</option>
                  {positions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                {errors.position_id && <p className="text-danger mt-1 text-sm">{errors.position_id}</p>}
              </div>
            </div>

            <div className="grid grid-cols-3 gap-3">
              <div>
                <label className="form-label">Quantity</label>
                <input type="number" min="1" className="form-input" value={data.quantity} onChange={(e) => setData('quantity', Number(e.target.value))} />
              </div>
              <div>
                <label className="form-label">By date</label>
                <input type="date" className="form-input" value={data.by_date} onChange={(e) => setData('by_date', e.target.value)} required />
                {errors.by_date && <p className="text-danger mt-1 text-sm">{errors.by_date}</p>}
              </div>
              <div>
                <label className="form-label">Urgency</label>
                <select className="form-select" value={data.urgency} onChange={(e) => setData('urgency', e.target.value)}>
                  {urgencies.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                </select>
              </div>
            </div>

            <div>
              <label className="form-label">Reason</label>
              <textarea className="form-input" rows={2} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
              {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
            </div>
            <div>
              <label className="form-label">Notes (optional)</label>
              <input className="form-input" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </div>

            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary px-4 py-2 font-semibold text-white" disabled={processing}>Submit</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
