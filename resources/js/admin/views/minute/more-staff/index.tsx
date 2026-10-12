import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import SidePanel from '@/components/SidePanel'
import { monthDay, Pill, Tone } from '@/components/timesheet/PmTimesheet'
import { cn } from '@/utils/helpers'
import { Head, router, useForm } from '@inertiajs/react'
import { FormEvent, useMemo, useState } from 'react'

type Position = { id: number; name: string }
type Property = { id: number; name: string; positions: Position[] }
type Option = { value: string; label: string }
type Req = {
  id: number
  property: string
  position: string
  quantity_requested: number
  quantity_fulfilled: number
  by_date: string
  urgency: string
  urgency_label: string
  status: string
  status_label: string
  is_overdue: boolean
  recruiter: string | null
  created_at: string | null
  closed_at: string | null
  reason: string | null
  notes: string | null
  placed: string[]
  decision_note: string | null
}
type Props = { properties: Property[]; requests: Req[]; urgencies: Option[]; can: { initiate: boolean }; open_form: boolean }

const OPEN = ['submitted', 'in_progress']
const TONE: Record<string, Tone> = { submitted: 'warning', in_progress: 'info', fulfilled: 'success', declined: 'danger', cancelled: 'muted' }

/** "in 10 days", "today", "3 days late". */
const dueIn = (date: string) => {
  const days = Math.round((new Date(date + 'T12:00:00').getTime() - new Date(new Date().toDateString() + ' 12:00').getTime()) / 86_400_000)
  if (days === 0) return 'today'
  if (days < 0) return `${-days} ${days === -1 ? 'day' : 'days'} late`
  return `in ${days} ${days === 1 ? 'day' : 'days'}`
}

const Page = ({ properties, requests, urgencies, can, open_form }: Props) => {
  const [creating, setCreating] = useState(open_form && can.initiate && properties.length > 0)
  const open = requests.filter((r) => OPEN.includes(r.status))
  const past = requests.filter((r) => !OPEN.includes(r.status))
  const many = properties.length > 1

  const cancel = (r: Req) =>
    confirmAction({
      title: 'Cancel staffing request',
      message: (
        <>
          Cancel the request for{' '}
          <strong className="text-default-900">
            {r.quantity_requested} × {r.position}
          </strong>{' '}
          at <strong className="text-default-900">{r.property}</strong>?
        </>
      ),
      input: { label: 'Reason', placeholder: 'No longer needed', required: true },
      confirmLabel: 'Cancel request',
      cancelLabel: 'Keep it',
      onConfirm: (reason) => router.post(`/staffing-requests/${r.id}/cancel`, { reason }, { preserveScroll: true }),
    })

  return (
    <>
      <Head title="Staffing requests" />
      <PageBreadcrumb title="Staffing requests" />
      <div className="-mt-3 mb-6 flex flex-wrap items-end justify-between gap-3">
        <p className="text-default-500">{many ? 'Your properties' : (properties[0]?.name ?? 'Your property')} · ask for more people, and see who's been placed</p>
        {can.initiate && properties.length > 0 && (
          <button className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white" onClick={() => setCreating(true)}>
            Request staff
          </button>
        )}
      </div>

      <section className="card mb-5" aria-labelledby="open-title">
        <div className="card-header">
          <h4 id="open-title" className="card-title">
            Open
          </h4>
        </div>
        <div className="card-body !py-0">
          {open.length ? (
            open.map((r) => (
              <div key={r.id} className="border-default-200 flex flex-wrap items-start gap-5 border-b border-dashed py-5 last:border-0">
                <div className="min-w-0 flex-[1_1_380px]">
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <Pill tone={TONE[r.status] ?? 'muted'}>{`${r.status_label}${r.recruiter ? ` · with ${r.recruiter}` : ''}`}</Pill>
                    <span className="text-default-500 text-sm">
                      {r.created_at && `Asked ${monthDay(r.created_at)} · `}
                      {r.urgency_label} urgency
                    </span>
                  </div>
                  <div className="text-default-900 mt-2.5 mb-1 text-lg font-semibold">
                    {r.quantity_requested} × {r.position}
                    {many && <span className="text-default-500 font-normal"> · {r.property}</span>}
                  </div>
                  {r.reason && <p className="max-w-2xl">{r.reason}</p>}
                  {r.notes && <p className="text-default-500 mt-1 text-sm">Your notes: {r.notes}</p>}
                </div>
                <div className="flex flex-[0_1_280px] flex-col gap-2">
                  <div className="flex justify-between gap-3">
                    <span className="text-default-500 text-sm">Needed by</span>
                    <strong className={cn('text-end', r.is_overdue ? 'text-danger' : 'text-default-900')}>
                      {monthDay(r.by_date)} · {dueIn(r.by_date)}
                    </strong>
                  </div>
                  <div className="flex justify-between gap-3">
                    <span className="text-default-500 text-sm">Placed</span>
                    <strong className="text-default-900">
                      {r.quantity_fulfilled} of {r.quantity_requested}
                    </strong>
                  </div>
                  <span className="bg-default-200 h-2 overflow-hidden rounded-full" aria-hidden="true">
                    <span className="bg-primary block h-full rounded-full" style={{ width: `${Math.max(2, (r.quantity_fulfilled / Math.max(1, r.quantity_requested)) * 100)}%` }} />
                  </span>
                  {r.placed.length > 0 && <span className="text-default-500 text-sm">{r.placed.join(', ')}</span>}
                  <button className="btn btn-sm border-danger/35 text-danger hover:bg-danger mt-1.5 self-end border px-3 font-semibold hover:text-white" onClick={() => cancel(r)}>
                    Cancel request…
                  </button>
                </div>
              </div>
            ))
          ) : (
            <p className="text-default-500 py-5">No open requests.{can.initiate && properties.length > 0 && ' Use Request staff when you need more people.'}</p>
          )}
        </div>
      </section>

      <section className="card" aria-labelledby="past-title">
        <div className="card-header">
          <h4 id="past-title" className="card-title">
            Past requests
          </h4>
        </div>
        {past.length ? (
          <div className="relative overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm">
              <thead>
                <tr className="bg-light/25 text-default-500 text-2xs uppercase">
                  <th className="py-2.5 ps-6 pe-3 text-start font-medium">Request</th>
                  <th className="px-3 py-2.5 text-start font-medium">Asked</th>
                  <th className="px-3 py-2.5 text-start font-medium">Placed</th>
                  <th className="py-2.5 ps-3 pe-6 text-start font-medium">Outcome</th>
                </tr>
              </thead>
              <tbody>
                {past.map((r) => (
                  <tr key={r.id} className="border-default-300 border-t align-top">
                    <td className="py-3 ps-6 pe-3">
                      <span className="text-default-900 font-semibold">
                        {r.quantity_requested} × {r.position}
                      </span>
                      {many && <span className="text-default-500 block text-xs">{r.property}</span>}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap">{r.created_at ? monthDay(r.created_at) : '—'}</td>
                    <td className="px-3 py-3">
                      {r.quantity_fulfilled} of {r.quantity_requested}
                      {r.placed.length > 0 && <span className="text-default-500 block text-xs">{r.placed.join(', ')}</span>}
                    </td>
                    <td className="py-3 ps-3 pe-6">
                      <Pill tone={TONE[r.status] ?? 'muted'}>{`${r.status_label}${r.closed_at ? ` ${monthDay(r.closed_at)}` : ''}`}</Pill>
                      {r.decision_note && <span className="text-default-500 mt-1 block text-xs">{r.decision_note}</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="card-body text-default-500">Filled, declined and cancelled requests show here, with who was placed.</p>
        )}
      </section>

      {creating && <RequestPanel properties={properties} urgencies={urgencies} onClose={() => setCreating(false)} />}
    </>
  )
}

const RequestPanel = ({ properties, urgencies, onClose }: { properties: Property[]; urgencies: Option[]; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<{
    property_id: number | string
    position_id: number | string
    quantity: number
    by_date: string
    urgency: string
    reason: string
    notes: string
  }>({
    property_id: properties[0]?.id ?? '',
    position_id: '',
    quantity: 1,
    by_date: '',
    urgency: 'normal',
    reason: '',
    notes: '',
  })

  const positions = useMemo(() => properties.find((p) => p.id === Number(data.property_id))?.positions ?? [], [properties, data.property_id])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/staffing-requests', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <SidePanel
      title="Request staff"
      subtitle="Your recruiter gets this right away"
      onClose={onClose}
      footer={
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4" onClick={onClose}>
            Cancel
          </button>
          <button type="submit" form="staff-request" className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white" disabled={processing}>
            Send request
          </button>
        </div>
      }>
      <form id="staff-request" onSubmit={submit} className="space-y-4">
        {properties.length > 1 && (
          <div>
            <label className="form-label" htmlFor="sr-property">
              Property
            </label>
            <select
              id="sr-property"
              className="form-select"
              value={data.property_id}
              onChange={(e) => {
                setData('property_id', e.target.value)
                setData('position_id', '')
              }}>
              {properties.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>
        )}
        <div className="grid grid-cols-3 gap-3">
          <div className="col-span-2">
            <label className="form-label" htmlFor="sr-position">
              Position
            </label>
            <select id="sr-position" className="form-select" value={data.position_id} onChange={(e) => setData('position_id', e.target.value)} required>
              <option value="">Select…</option>
              {positions.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
            {errors.position_id && <p className="text-danger mt-1 text-sm">{errors.position_id}</p>}
          </div>
          <div>
            <label className="form-label" htmlFor="sr-qty">
              How many
            </label>
            <input id="sr-qty" type="number" min="1" className="form-input" value={data.quantity} onChange={(e) => setData('quantity', Number(e.target.value))} />
            {errors.quantity && <p className="text-danger mt-1 text-sm">{errors.quantity}</p>}
          </div>
        </div>
        <div>
          <label className="form-label" htmlFor="sr-by">
            Needed by
          </label>
          <input id="sr-by" type="date" className="form-input" value={data.by_date} onChange={(e) => setData('by_date', e.target.value)} required />
          {errors.by_date && <p className="text-danger mt-1 text-sm">{errors.by_date}</p>}
        </div>
        <fieldset>
          <legend className="form-label">Urgency</legend>
          <div className="bg-light inline-flex w-full rounded-lg p-0.5" role="group" aria-label="Urgency">
            {urgencies.map((u) => (
              <button
                key={u.value}
                type="button"
                aria-pressed={data.urgency === u.value}
                onClick={() => setData('urgency', u.value)}
                className={cn('flex-1 rounded-md px-2 py-1.5 text-sm', data.urgency === u.value ? 'bg-card text-default-900 font-semibold shadow-sm' : 'text-default-500')}>
                {u.label}
              </button>
            ))}
          </div>
        </fieldset>
        <div>
          <label className="form-label" htmlFor="sr-reason">
            Why you need them
          </label>
          <textarea
            id="sr-reason"
            className="form-textarea"
            rows={3}
            placeholder="e.g. Two housekeepers leave at the end of the month."
            value={data.reason}
            onChange={(e) => setData('reason', e.target.value)}
            required
          />
          {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
        </div>
        <div>
          <label className="form-label" htmlFor="sr-notes">
            Notes for the recruiter <span className="text-default-500 font-normal">(optional)</span>
          </label>
          <textarea
            id="sr-notes"
            className="form-textarea"
            rows={3}
            placeholder="Shift, skills, who they report to, dress code…"
            value={data.notes}
            onChange={(e) => setData('notes', e.target.value)}
          />
        </div>
      </form>
    </SidePanel>
  )
}

export default Page
