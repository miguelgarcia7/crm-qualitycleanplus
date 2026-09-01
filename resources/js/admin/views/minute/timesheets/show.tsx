import PageBreadcrumb from '@/components/PageBreadcrumb'
import { formatClockTime } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Row = { work_order_id: number; contractor: string | null; position: string | null }
type Entry = { id: number; work_order_id: number; date: string | null; start_time: string | null; end_time: string | null }
type Summary = { regular_minutes: number; overtime_minutes: number; training_minutes: number; total_pay: number; total_bill: number }

type Props = {
  timesheet: { id: number; status: string; status_label: string; decline_reason: string | null }
  property: { id: number; name: string }
  week: { start: string; end: string; days: string[] }
  rows: Row[]
  entries: Entry[]
  summaries: Record<number, Summary>
  can: { decide: boolean }
}

const hrs = (min: number | null | undefined) => ((min ?? 0) / 60).toFixed(2)
const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const dayLabel = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'numeric', day: 'numeric' })

const Page = ({ timesheet, property, week, rows, entries, summaries, can }: Props) => {
  const [declining, setDeclining] = useState(false)
  const cellEntries = (wo: number, date: string) => entries.filter((e) => e.work_order_id === wo && e.date === date)

  const approve = () => {
    if (confirm('Approve this timesheet? This generates the invoice.')) {
      router.post(`/timesheets/${timesheet.id}/approve`)
    }
  }

  return (
    <>
      <Head title={`Timesheet — ${property.name}`} />
      <PageBreadcrumb title={property.name} subtitle={`Week of ${week.start}`} />

      <div className="card">
        <div className="card-header">
          <div className="flex items-center gap-3">
            <h4 className="card-title">Week of {week.start}</h4>
            <span className="badge badge-label bg-secondary/15 text-secondary">{timesheet.status_label}</span>
          </div>
          <div className="flex items-center gap-2">
            {can.decide && (
              <>
                <button className="btn bg-success hover:bg-success-hover px-4 py-1.5 font-semibold text-white" onClick={approve}>Approve</button>
                <button className="btn bg-danger/15 text-danger hover:bg-danger hover:text-white px-4 py-1.5 font-semibold" onClick={() => setDeclining(true)}>Decline</button>
              </>
            )}
            <Link href="/timesheets" className="text-default-500 ms-2 text-sm hover:underline">All</Link>
          </div>
        </div>

        <div className="table-wrapper">
          <table className="table table-hover text-sm">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-2xs uppercase">
                <th>Contractor</th>
                {week.days.map((d) => (<th key={d} className="text-center">{dayLabel(d)}</th>))}
                <th className="text-end">Reg / OT</th>
                <th className="text-end">Pay / Bill</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => {
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
                          <div key={e.id} className="whitespace-nowrap">
                            {formatClockTime(e.start_time)} – {formatClockTime(e.end_time)}
                          </div>
                        ))}
                      </td>
                    ))}
                    <td className="text-end whitespace-nowrap">{hrs(s?.regular_minutes)} / {hrs(s?.overtime_minutes)}</td>
                    <td className="text-end whitespace-nowrap">{money(s?.total_pay ?? 0)} / {money(s?.total_bill ?? 0)}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>

      {declining && <DeclineModal timesheetId={timesheet.id} onClose={() => setDeclining(false)} />}
    </>
  )
}

const DeclineModal = ({ timesheetId, onClose }: { timesheetId: number; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({ reason: '', category: '' })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/timesheets/${timesheetId}/decline`, { onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">Decline Timesheet</h4></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Reason</label>
              <textarea className="form-input" rows={3} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
              {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
            </div>
            <div>
              <label className="form-label">Category (optional)</label>
              <select className="form-select" value={data.category} onChange={(e) => setData('category', e.target.value)}>
                <option value="">—</option>
                <option value="Wrong Hours">Wrong Hours</option>
                <option value="Wrong Rate">Wrong Rate</option>
                <option value="Unauthorized Work">Unauthorized Work</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-danger hover:bg-danger-hover px-4 py-2 font-semibold text-white" disabled={processing}>Decline</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
