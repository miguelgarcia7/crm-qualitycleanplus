import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useEffect, useState } from 'react'

type Row = { work_order_id: number; person_id: number; contractor: string | null; position: string | null }
type Entry = {
  id: number
  work_order_id: number
  date: string | null
  start_time: string | null
  end_time: string | null
  duration_minutes: number | null
  entry_type: string
}
type Summary = { regular_minutes: number; overtime_minutes: number; training_minutes: number; total_pay: number; total_bill: number }

type Timesheet = { id: number; status: string; status_label: string; decline_reason: string | null }
type Adjustment = {
  id: number
  person: string
  person_id: number
  work_order_id: number | null
  type: string
  value: number
  is_billable: boolean
  notes: string | null
  source_type: string
}

type Props = {
  property: { id: number; name: string; timezone: string }
  week: { start: string; end: string; days: string[] }
  period: { id: number; status: string } | null
  timesheet: Timesheet | null
  rows: Row[]
  entries: Entry[]
  summaries: Record<number, Summary>
  adjustments: Adjustment[]
  can: { edit: boolean; submit: boolean; adjust: boolean }
}

const hrs = (min: number | null | undefined) => ((min ?? 0) / 60).toFixed(2)
const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const dayLabel = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'numeric', day: 'numeric' })

const Page = ({ property, week, period, timesheet, rows, entries, summaries, adjustments, can }: Props) => {
  const [modal, setModal] = useState<{ workOrderId: number; date: string } | null>(null)
  const [adjustModal, setAdjustModal] = useState(false)

  const submitForApproval = () => {
    if (timesheet && confirm('Send this week to the property manager for approval?')) {
      router.post(`/admin/timesheets/${timesheet.id}/submit`, {}, { preserveScroll: true })
    }
  }

  // Live updates (Reverb): refresh the grid when anyone changes this property's
  // entries for the week currently in view.
  useEffect(() => {
    if (typeof window === 'undefined' || !window.Echo) return
    const channel = window.Echo.private(`property.${property.id}`)
    channel.listen('.time-entry.saved', (e: { week_start: string }) => {
      if (e.week_start === week.start) {
        router.reload({ only: ['entries', 'summaries'] })
      }
    })
    return () => {
      window.Echo.leave(`property.${property.id}`)
    }
  }, [property.id, week.start])

  const shiftWeek = (dir: -1 | 1) => {
    const d = new Date(week.start + 'T00:00:00')
    d.setDate(d.getDate() + dir * 7)
    router.get(`/admin/properties/${property.id}/grid`, { week: d.toISOString().slice(0, 10) }, { preserveScroll: true })
  }

  const cellEntries = (wo: number, date: string) => entries.filter((e) => e.work_order_id === wo && e.date === date)

  return (
    <>
      <Head title={`Timesheet — ${property.name}`} />
      <PageBreadcrumb title={property.name} subtitle="Weekly Timesheet" />

      <div className="card">
        <div className="card-header">
          <div className="flex items-center gap-3">
            <h4 className="card-title">
              Week of {week.start}
              {timesheet && <span className="badge badge-label bg-secondary/15 text-secondary ms-2">{timesheet.status_label}</span>}
            </h4>
          </div>
          <div className="flex items-center gap-2">
            <button className="btn btn-light px-3 py-1.5" onClick={() => shiftWeek(-1)}>← Prev</button>
            <button className="btn btn-light px-3 py-1.5" onClick={() => shiftWeek(1)}>Next →</button>
            {can.submit && (
              <button className="btn bg-primary hover:bg-primary-hover px-4 py-1.5 font-semibold text-white" onClick={submitForApproval}>
                Send for Approval
              </button>
            )}
            <Link href={`/admin/properties/${property.id}`} className="text-default-500 ms-2 text-sm hover:underline">Property</Link>
          </div>
        </div>

        {timesheet?.status === 'declined' && timesheet.decline_reason && (
          <div className="bg-danger/10 text-danger mx-6 mb-4 rounded-lg px-4 py-3 text-sm">
            <strong>Declined:</strong> {timesheet.decline_reason}
          </div>
        )}

        {!period ? (
          <div className="card-body text-default-400 p-6">No payroll period for this week yet.</div>
        ) : (
          <div className="table-wrapper">
            <table className="table table-hover text-sm">
              <thead className="thead-sm">
                <tr className="bg-light/25 text-xs uppercase">
                  <th>Contractor</th>
                  {week.days.map((d) => (
                    <th key={d} className="text-center">{dayLabel(d)}</th>
                  ))}
                  <th className="text-end">Reg / OT</th>
                  <th className="text-end">Pay / Bill</th>
                </tr>
              </thead>
              <tbody>
                {rows.length ? (
                  rows.map((r) => {
                    const s = summaries[r.work_order_id]
                    return (
                      <tr key={r.work_order_id}>
                        <td>
                          <div className="font-medium">{r.contractor}</div>
                          <div className="text-default-400 text-xs">{r.position}</div>
                        </td>
                        {week.days.map((d) => (
                          <td key={d} className="text-center align-top">
                            {cellEntries(r.work_order_id, d).map((e) => (
                              <div key={e.id} className="mb-1 flex items-center justify-center gap-1">
                                <span>{e.start_time}–{e.end_time}</span>
                                {can.edit && (
                                  <button className="text-danger" title="Remove"
                                    onClick={() => router.delete(`/admin/time-entries/${e.id}`, { preserveScroll: true })}>×</button>
                                )}
                              </div>
                            ))}
                            {can.edit && (
                              <button className="text-primary text-xs hover:underline" onClick={() => setModal({ workOrderId: r.work_order_id, date: d })}>
                                + add
                              </button>
                            )}
                          </td>
                        ))}
                        <td className="text-end whitespace-nowrap">{hrs(s?.regular_minutes)} / {hrs(s?.overtime_minutes)}</td>
                        <td className="text-end whitespace-nowrap">{money(s?.total_pay ?? 0)} / {money(s?.total_bill ?? 0)}</td>
                      </tr>
                    )
                  })
                ) : (
                  <tr>
                    <td colSpan={week.days.length + 3} className="text-default-400 py-4 text-center">No active work orders at this property.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {period && (
        <div className="card mt-4">
          <div className="card-header">
            <h4 className="card-title">Adjustments</h4>
            {can.adjust && (
              <button className="btn btn-light px-3 py-1.5" onClick={() => setAdjustModal(true)}>+ Add adjustment</button>
            )}
          </div>
          <div className="table-wrapper">
            <table className="table table-hover text-sm">
              <thead className="thead-sm">
                <tr className="bg-light/25 text-xs uppercase">
                  <th>Contractor</th>
                  <th>Type</th>
                  <th className="text-end">Amount</th>
                  <th>Billable</th>
                  <th>Source</th>
                  <th>Notes</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {adjustments.length ? (
                  adjustments.map((a) => (
                    <tr key={a.id}>
                      <td>{a.person}</td>
                      <td>
                        <span className={`badge badge-label ${a.type === 'incentive' ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger'}`}>{a.type}</span>
                      </td>
                      <td className="text-end whitespace-nowrap">{a.type === 'deduction' ? '−' : '+'}{money(a.value)}</td>
                      <td>{a.is_billable ? 'Yes' : 'No'}</td>
                      <td className="text-default-400">{a.source_type === 'manual' ? 'Manual' : a.source_type.replaceAll('_', ' ')}</td>
                      <td className="text-default-400">{a.notes}</td>
                      <td className="text-end">
                        {can.adjust && a.source_type === 'manual' && (
                          <button className="text-danger" title="Remove"
                            onClick={() => router.delete(`/admin/adjustments/${a.id}`, { preserveScroll: true })}>×</button>
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr><td colSpan={7} className="text-default-400 py-4 text-center">No adjustments for this week.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {modal && <AddEntryModal workOrderId={modal.workOrderId} date={modal.date} onClose={() => setModal(null)} />}
      {adjustModal && period && (
        <AddAdjustmentModal periodId={period.id} rows={rows} onClose={() => setAdjustModal(false)} />
      )}
    </>
  )
}

const AddEntryModal = ({ workOrderId, date, onClose }: { workOrderId: number; date: string; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({
    date,
    start_time: '09:00',
    end_time: '17:00',
    entry_type: 'work',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/work-orders/${workOrderId}/time-entries`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">Add Time Entry</h4></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Date</label>
              <input type="date" className="form-input" value={data.date} onChange={(e) => setData('date', e.target.value)} required />
              {errors.date && <p className="text-danger mt-1 text-sm">{errors.date}</p>}
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="form-label">Start</label>
                <input type="time" className="form-input" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} required />
              </div>
              <div>
                <label className="form-label">End</label>
                <input type="time" className="form-input" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} required />
              </div>
            </div>
            <div>
              <label className="form-label">Type</label>
              <select className="form-select" value={data.entry_type} onChange={(e) => setData('entry_type', e.target.value)}>
                <option value="work">Work</option>
                <option value="training">Training</option>
              </select>
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Add</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

const AddAdjustmentModal = ({ periodId, rows, onClose }: { periodId: number; rows: Row[]; onClose: () => void }) => {
  const { data, setData, processing, errors } = useForm<{
    work_order_id: number | string; amount: string; type: string; is_billable: boolean; notes: string
  }>({
    work_order_id: rows[0]?.work_order_id ?? '',
    amount: '',
    type: 'deduction',
    is_billable: false,
    notes: '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const row = rows.find((r) => r.work_order_id === Number(data.work_order_id))
    router.post(
      `/admin/payroll-periods/${periodId}/adjustments`,
      {
        person_id: row?.person_id,
        work_order_id: data.work_order_id,
        amount: data.amount,
        type: data.type,
        is_billable: data.type === 'incentive' ? data.is_billable : false,
        notes: data.notes,
      },
      { preserveScroll: true, onSuccess: onClose },
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">Add Adjustment</h4></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Contractor</label>
              <select className="form-select" value={data.work_order_id} onChange={(e) => setData('work_order_id', e.target.value)} required>
                {rows.map((r) => (
                  <option key={r.work_order_id} value={r.work_order_id}>{r.contractor} — {r.position}</option>
                ))}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="form-label">Type</label>
                <select className="form-select" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                  <option value="deduction">Deduction</option>
                  <option value="incentive">Incentive</option>
                </select>
              </div>
              <div>
                <label className="form-label">Amount ($)</label>
                <input type="number" step="0.01" min="0.01" className="form-input" value={data.amount} onChange={(e) => setData('amount', e.target.value)} required />
                {errors.amount && <p className="text-danger mt-1 text-sm">{errors.amount}</p>}
              </div>
            </div>
            {data.type === 'incentive' && (
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="form-checkbox" checked={data.is_billable} onChange={(e) => setData('is_billable', e.target.checked)} />
                Billable (adds to the property invoice)
              </label>
            )}
            <div>
              <label className="form-label">Notes</label>
              <input type="text" className="form-input" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Add</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
