import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

type Changes = { name?: string; email?: string; phone?: string }
type MineItem = { id: number; status: string; status_label: string; changes: Changes; created_at: string | null }
type Props = {
  current: { name: string; email: string; phone: string | null }
  mine: MineItem[]
  can: { initiate: boolean }
}

const FIELDS: Array<keyof Changes> = ['name', 'email', 'phone']
const statusBadge = (s: string) =>
  s === 'completed' ? 'badge-soft-success' : s === 'rejected' || s === 'cancelled' ? 'badge-soft-danger' : 'badge-soft-primary'

const Page = ({ current, mine, can }: Props) => {
  const { data, setData, post, processing, errors } = useForm<{ name: string; email: string; phone: string; reason: string }>({
    name: '', email: '', phone: '', reason: '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/my-info', { preserveScroll: true, onSuccess: () => setData({ name: '', email: '', phone: '', reason: '' }) })
  }

  return (
    <>
      <Head title="My Info" />
      <PageBreadcrumb title="My Info" subtitle="QC Minute" />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="card rounded-2xl">
          <div className="card-header p-6"><h4 className="card-title">Request a Change</h4></div>
          <div className="card-body p-6">
            <div className="bg-light/40 mb-4 rounded-lg p-3 text-sm">
              <div><span className="text-default-400">Name:</span> {current.name}</div>
              <div><span className="text-default-400">Email:</span> {current.email}</div>
              <div><span className="text-default-400">Phone:</span> {current.phone || '—'}</div>
            </div>
            {can.initiate ? (
              <form onSubmit={submit} className="space-y-3">
                <p className="text-default-400 text-xs">Fill only what should change. HR will verify before it's applied.</p>
                <div><label className="form-label">New name</label><input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder={current.name} />{errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}</div>
                <div><label className="form-label">New email</label><input type="email" className="form-input" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder={current.email} />{errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}</div>
                <div><label className="form-label">New phone</label><input className="form-input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder={current.phone ?? ''} /></div>
                <div><label className="form-label">Reason</label><textarea className="form-input" rows={2} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />{errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}</div>
                <button type="submit" className="btn w-full bg-primary py-2 font-semibold text-white" disabled={processing}>Submit for verification</button>
              </form>
            ) : <p className="text-default-500 text-sm">You don't have permission to request profile changes.</p>}
          </div>
        </div>

        <div className="card rounded-2xl">
          <div className="card-header p-6"><h4 className="card-title">My Requests</h4></div>
          <div className="table-wrapper">
            <table className="table table-hover text-sm">
              <thead className="thead-sm"><tr className="bg-light/25 text-2xs uppercase"><th>Change</th><th>Submitted</th><th>Status</th></tr></thead>
              <tbody>
                {mine.length ? mine.map((m) => (
                  <tr key={m.id}>
                    <td>{FIELDS.filter((f) => m.changes[f] !== undefined).map((f) => `${f}→${m.changes[f]}`).join(', ')}</td>
                    <td>{m.created_at}</td>
                    <td><span className={`badge ${statusBadge(m.status)}`}>{m.status_label}</span></td>
                  </tr>
                )) : <tr><td colSpan={3} className="text-default-400 py-4 text-center">No requests yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
