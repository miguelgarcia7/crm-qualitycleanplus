import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Balance = { bucket: string; label: string; available: number; pending: number; allotted: number }
type Req = { id: number; person?: string; bucket: string; start_date: string; end_date: string; hours: number; status: string; reason?: string; cancellable?: boolean }
type Other = { person_id: number; name: string; balances: Balance[] | null }

type Props = {
  myBalances: Balance[] | null
  noticePeriod: boolean
  myRequests: Req[]
  queue: Req[]
  others: Other[]
  can: { submit: boolean; approve: boolean; viewAll: boolean; adjust: boolean }
}

const statusTone = (s: string) =>
  s === 'approved' ? 'badge-soft-success' : s === 'pending' ? 'badge-soft-warning' : 'badge-soft-secondary'

const Page = ({ myBalances, noticePeriod, myRequests, queue, others, can }: Props) => {
  const form = useForm({ bucket: 'vacation', start_date: '', end_date: '', hours: 8, reason: '', notice_period_warning_acknowledged: false })
  const [adjustFor, setAdjustFor] = useState<Other | null>(null)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post('/admin/pto', { preserveScroll: true, onSuccess: () => form.reset('hours', 'reason', 'start_date', 'end_date') })
  }

  const reject = (id: number) => {
    const reason = prompt('Reason for rejection?')
    if (reason) router.post(`/admin/pto/${id}/reject`, { reason }, { preserveScroll: true })
  }
  const cancel = (id: number) => {
    if (confirm('Cancel this PTO request? Hours return to your balance.')) {
      router.post(`/admin/pto/${id}/cancel`, {}, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head title="Time Off" />
      <PageBreadcrumb title="Time Off" subtitle="PTO" />

      {myBalances && (
        <div className="mb-4 grid gap-4 sm:grid-cols-3">
          {myBalances.map((b) => (
            <div key={b.bucket} className="card rounded-2xl">
              <div className="card-body p-5">
                <h5 className="text-default-400 text-sm uppercase">{b.label}</h5>
                <h3 className="mt-1 text-2xl font-semibold">{b.available}h <span className="text-default-400 text-sm font-normal">available</span></h3>
                <p className="text-default-400 mt-1 text-xs">{b.pending}h pending · {b.allotted}h allotted</p>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        {can.submit && (
          <div className="card rounded-2xl">
            <div className="card-body p-5">
              <h4 className="card-title mb-3">Request time off</h4>
              {noticePeriod && (
                <div className="bg-warning/10 text-warning mb-3 rounded-lg p-3 text-sm">
                  A termination is in progress for you. Vacation during a notice period is generally not permitted.
                  <label className="mt-2 flex items-center gap-2">
                    <input type="checkbox" checked={form.data.notice_period_warning_acknowledged} onChange={(e) => form.setData('notice_period_warning_acknowledged', e.target.checked)} />
                    I acknowledge
                  </label>
                </div>
              )}
              <form onSubmit={submit} className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                  <label>
                    <span className="form-label">Bucket</span>
                    <select className="form-select w-full" value={form.data.bucket} onChange={(e) => form.setData('bucket', e.target.value)}>
                      <option value="vacation">Vacation</option>
                      <option value="scheduled">Scheduled</option>
                      <option value="unscheduled">Unscheduled</option>
                    </select>
                  </label>
                  <label>
                    <span className="form-label">Hours</span>
                    <input type="number" step="0.5" min="0.5" className="form-input w-full" value={form.data.hours} onChange={(e) => form.setData('hours', Number(e.target.value))} />
                  </label>
                  <label>
                    <span className="form-label">Start</span>
                    <input type="date" className="form-input w-full" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} required />
                  </label>
                  <label>
                    <span className="form-label">End</span>
                    <input type="date" className="form-input w-full" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} required />
                  </label>
                </div>
                <label className="block">
                  <span className="form-label">Reason</span>
                  <input className="form-input w-full" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                </label>
                {form.errors.hours && <p className="text-danger text-sm">{form.errors.hours}</p>}
                <button className="btn bg-primary w-full py-2 font-semibold text-white" disabled={form.processing}>
                  Submit request
                </button>
              </form>
            </div>
          </div>
        )}

        <div className="card rounded-2xl">
          <div className="card-header p-5 pb-2"><h4 className="card-title">My requests</h4></div>
          <div className="card-body p-5 pt-0">
            {myRequests.length === 0 ? (
              <p className="text-default-400 py-4 text-sm">No requests yet.</p>
            ) : (
              myRequests.map((r) => (
                <div key={r.id} className="border-default-100 flex items-center justify-between border-b py-2.5 last:border-0">
                  <div>
                    <div className="text-sm font-medium">{r.bucket} · {r.hours}h</div>
                    <div className="text-default-400 text-xs">{r.start_date} → {r.end_date}</div>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={`badge ${statusTone(r.status)} capitalize`}>{r.status}</span>
                    {r.cancellable && <button className="btn btn-sm btn-light text-danger" onClick={() => cancel(r.id)}>Cancel</button>}
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      {can.approve && (
        <div className="card mt-4 rounded-2xl">
          <div className="card-header p-5 pb-2"><h4 className="card-title">Approval queue</h4></div>
          <div className="card-body p-5 pt-0">
            {queue.length === 0 ? (
              <p className="text-default-400 py-4 text-sm">Nothing awaiting approval.</p>
            ) : (
              queue.map((r) => (
                <div key={r.id} className="border-default-100 flex items-center justify-between border-b py-2.5 last:border-0">
                  <div>
                    <div className="text-sm font-medium">{r.person} · {r.bucket} · {r.hours}h</div>
                    <div className="text-default-400 text-xs">{r.start_date} → {r.end_date}{r.reason ? ` · ${r.reason}` : ''}</div>
                  </div>
                  <div className="flex gap-2">
                    <button className="btn btn-sm bg-primary text-white" onClick={() => router.post(`/admin/pto/${r.id}/approve`, {}, { preserveScroll: true })}>Approve</button>
                    <button className="btn btn-sm btn-light text-danger" onClick={() => reject(r.id)}>Reject</button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {can.viewAll && (
        <div className="card mt-4 rounded-2xl">
          <div className="card-header p-5 pb-2"><h4 className="card-title">Staff balances</h4></div>
          <div className="card-body p-5 pt-0">
            <table className="w-full text-sm">
              <thead className="text-muted text-left"><tr><th className="py-2">Staff</th><th>Vacation</th><th>Scheduled</th><th>Unscheduled</th>{can.adjust && <th></th>}</tr></thead>
              <tbody>
                {others.map((o) => (
                  <tr key={o.person_id} className="border-default-100 border-t">
                    <td className="py-2 font-medium">{o.name}</td>
                    {o.balances ? (
                      o.balances.map((b) => <td key={b.bucket}>{b.available}h</td>)
                    ) : (
                      <td colSpan={3} className="text-default-400">No allotment</td>
                    )}
                    {can.adjust && <td className="text-right"><button className="btn btn-sm btn-light" onClick={() => setAdjustFor(o)}>Adjust</button></td>}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {adjustFor && <AdjustModal other={adjustFor} onClose={() => setAdjustFor(null)} />}
    </>
  )
}

const AdjustModal = ({ other, onClose }: { other: Other; onClose: () => void }) => {
  const form = useForm({ person_id: other.person_id, vacation_hours: 0, scheduled_hours: 0, unscheduled_hours: 0, reason: '' })
  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post('/admin/pto/adjust', { preserveScroll: true, onSuccess: onClose })
  }
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="card w-full max-w-md rounded-2xl" onClick={(e) => e.stopPropagation()}>
        <div className="card-body p-6">
          <h4 className="mb-3 font-semibold">Adjust balance — {other.name}</h4>
          <form onSubmit={submit} className="space-y-3">
            {(['vacation_hours', 'scheduled_hours', 'unscheduled_hours'] as const).map((k) => (
              <label key={k} className="block">
                <span className="form-label capitalize">{k.replace('_hours', '')} (± hours)</span>
                <input type="number" step="0.5" className="form-input w-full" value={form.data[k]} onChange={(e) => form.setData(k, Number(e.target.value))} />
              </label>
            ))}
            <label className="block">
              <span className="form-label">Reason</span>
              <input className="form-input w-full" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} required />
            </label>
            {form.errors.reason && <p className="text-danger text-sm">{form.errors.reason}</p>}
            <div className="flex gap-2">
              <button className="btn bg-primary px-5 py-2 font-semibold text-white" disabled={form.processing}>Apply</button>
              <button type="button" className="btn btn-light px-5 py-2" onClick={onClose}>Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
