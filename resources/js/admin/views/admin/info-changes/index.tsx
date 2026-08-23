import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Changes = { name?: string; email?: string; phone?: string }
type QueueItem = {
  id: number; person: string; requested_by: string | null; reason: string | null
  current: { name: string; email: string; phone: string | null }; changes: Changes
}
type MineItem = { id: number; status: string; changes: Changes; created_at: string | null }
type PersonOption = { id: number; name: string; email: string; phone: string | null }
type Props = {
  queue: QueueItem[]; mine: MineItem[]; people: PersonOption[]
  can: { verify: boolean; initiate: boolean }
}

const FIELDS: Array<keyof Changes> = ['name', 'email', 'phone']

const changeList = (current: QueueItem['current'], changes: Changes) =>
  FIELDS.filter((f) => changes[f] !== undefined).map((f) => (
    <div key={f} className="text-xs">
      <span className="text-default-400 uppercase">{f}: </span>
      <span className="line-through">{current[f] || '—'}</span> → <span className="text-success font-medium">{changes[f]}</span>
    </div>
  ))

const Page = ({ queue, mine, people, can }: Props) => {
  const [onBehalf, setOnBehalf] = useState(false)

  return (
    <>
      <Head title="Info Changes" />
      <PageBreadcrumb title="Info Changes" subtitle="People" />

      {can.verify && (
        <div className="card">
          <div className="card-header">
            <h4 className="card-title">Pending Verification</h4>
            {can.initiate && <button className="btn bg-primary/15 text-primary hover:bg-primary hover:text-white px-4 py-1.5" onClick={() => setOnBehalf(true)}>+ Request on behalf</button>}
          </div>
          <div className="table-wrapper">
            <table className="table table-hover text-sm">
              <thead className="thead-sm">
                <tr className="bg-light/25 text-xs uppercase"><th>Person</th><th>Proposed change</th><th>Requested by</th><th className="text-end">Action</th></tr>
              </thead>
              <tbody>
                {queue.length ? queue.map((q) => (
                  <tr key={q.id}>
                    <td className="font-medium">{q.person}</td>
                    <td>{changeList(q.current, q.changes)}{q.reason && <div className="text-default-400 mt-1 text-xs italic">{q.reason}</div>}</td>
                    <td>{q.requested_by ?? '—'}</td>
                    <td className="text-end whitespace-nowrap">
                      <div className="flex justify-end gap-1.5">
                        <button className="btn bg-success/15 text-success hover:bg-success hover:text-white" onClick={() => router.post(`/admin/info-changes/${q.id}/approve`, {}, { preserveScroll: true })}>Approve</button>
                        <button className="btn bg-danger/15 text-danger hover:bg-danger hover:text-white" onClick={() => { const reason = window.prompt('Reason for declining?'); if (reason) router.post(`/admin/info-changes/${q.id}/decline`, { reason }, { preserveScroll: true }) }}>Decline</button>
                      </div>
                    </td>
                  </tr>
                )) : <tr><td colSpan={4} className="text-default-400 py-4 text-center">Nothing awaiting verification.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <div className="card mt-4">
        <div className="card-header">
          <h4 className="card-title">My Requests</h4>
          {!can.verify && can.initiate && <button className="btn bg-primary/15 text-primary hover:bg-primary hover:text-white px-4 py-1.5" onClick={() => setOnBehalf(true)}>+ New request</button>}
        </div>
        <div className="table-wrapper">
          <table className="table table-hover text-sm">
            <thead className="thead-sm"><tr className="bg-light/25 text-xs uppercase"><th>Change</th><th>Submitted</th><th>Status</th></tr></thead>
            <tbody>
              {mine.length ? mine.map((m) => (
                <tr key={m.id}>
                  <td>{FIELDS.filter((f) => m.changes[f] !== undefined).map((f) => `${f}→${m.changes[f]}`).join(', ')}</td>
                  <td>{m.created_at}</td>
                  <td><span className="badge badge-label bg-secondary/15 text-secondary">{m.status}</span></td>
                </tr>
              )) : <tr><td colSpan={3} className="text-default-400 py-4 text-center">No requests yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      {onBehalf && <OnBehalfModal people={people} onClose={() => setOnBehalf(false)} />}
    </>
  )
}

const OnBehalfModal = ({ people, onClose }: { people: PersonOption[]; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<{
    person_id: number | string; name: string; email: string; phone: string; reason: string
  }>({ person_id: '', name: '', email: '', phone: '', reason: '' })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/info-changes', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">Request Info Change</h4></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Person</label>
              <select className="form-select" value={data.person_id} onChange={(e) => setData('person_id', e.target.value)} required>
                <option value="">Select…</option>
                {people.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              {errors.person_id && <p className="text-danger mt-1 text-sm">{errors.person_id}</p>}
            </div>
            <p className="text-default-400 text-xs">Fill only the fields that should change.</p>
            <div><label className="form-label">New name</label><input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} />{errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}</div>
            <div><label className="form-label">New email</label><input type="email" className="form-input" value={data.email} onChange={(e) => setData('email', e.target.value)} />{errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}</div>
            <div><label className="form-label">New phone</label><input className="form-input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} /></div>
            <div><label className="form-label">Reason</label><textarea className="form-input" rows={2} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />{errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}</div>
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
