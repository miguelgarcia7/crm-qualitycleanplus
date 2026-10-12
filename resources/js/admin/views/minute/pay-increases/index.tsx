import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import SidePanel from '@/components/SidePanel'
import { money, Pill, Tone } from '@/components/timesheet/PmTimesheet'
import { Head, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Req = {
  id: number
  work_order_id: number | null
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
  decided_at: string | null
  can_change: boolean
}
type WoOption = { id: number; label: string; bill_rate: number }
type Props = {
  requests: Req[]
  workOrders: WoOption[]
  can: { initiate: boolean }
  open_form: boolean
  preselect: number | null
  scope: string | null
  many_properties: boolean
}

const STATUS: Record<string, { label: string; tone: Tone }> = {
  in_progress: { label: 'Waiting for approval', tone: 'warning' },
  completed: { label: 'Approved', tone: 'success' },
  rejected: { label: 'Declined', tone: 'danger' },
  cancelled: { label: 'Cancelled', tone: 'muted' },
}

const perHour = (cents: number) => `${money(cents)} / h`

const Page = ({ requests, workOrders, can, open_form, preselect, scope, many_properties: many }: Props) => {
  const canCreate = can.initiate && workOrders.length > 0
  const [creating, setCreating] = useState(open_form && canCreate)
  const [editing, setEditing] = useState<Req | null>(null)
  const open = requests.filter((r) => r.status === 'in_progress')
  const decided = requests.filter((r) => r.status !== 'in_progress')

  const cancel = (r: Req) =>
    confirmAction({
      title: 'Cancel pay increase request',
      message: (
        <>
          Cancel the <strong className="text-default-900">+{perHour(r.increase)}</strong> request for{' '}
          <strong className="text-default-900">{r.contractor}</strong>? Your recruiter will no longer see it.
        </>
      ),
      confirmLabel: 'Cancel request',
      cancelLabel: 'Keep it',
      onConfirm: () => router.post(`/pay-increases/${r.id}/cancel`, {}, { preserveScroll: true }),
    })

  return (
    <>
      <Head title="Pay increases" />
      <PageBreadcrumb title="Pay increases" />
      <div className="-mt-3 mb-6 flex flex-wrap items-end justify-between gap-3">
        <p className="text-default-500">{scope ?? 'Your property'} · ask for a raise for someone placed with you</p>
        {canCreate && (
          <button className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white" onClick={() => setCreating(true)}>
            Request a pay increase
          </button>
        )}
      </div>

      <section className="card mb-5" aria-labelledby="open-title">
        <div className="card-header">
          <h4 id="open-title" className="card-title">
            Waiting on your recruiter
          </h4>
        </div>
        <div className="card-body !py-0">
          {open.length ? (
            open.map((r) => (
              <div key={r.id} className="border-default-200 flex flex-wrap items-start gap-5 border-b border-dashed py-5 last:border-0">
                <div className="min-w-0 flex-[1_1_380px]">
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <Pill tone="warning">Waiting for approval</Pill>
                    {r.created_at && <span className="text-default-500 text-sm">Asked {r.created_at}</span>}
                  </div>
                  <div className="text-default-900 mt-2.5 mb-1 text-lg font-semibold">
                    {r.contractor ?? 'A contractor'}
                    <span className="text-default-500 font-normal">
                      {r.position && ` · ${r.position}`}
                      {many && r.property && ` · ${r.property}`}
                    </span>
                  </div>
                  {r.reason && <p>“{r.reason}”</p>}
                </div>
                <div className="flex flex-[0_1_300px] flex-col gap-2">
                  <div className="flex justify-between gap-3">
                    <span className="text-default-500 text-sm">Increase</span>
                    <strong className="text-default-900">+{perHour(r.increase)}</strong>
                  </div>
                  {r.current_bill_rate !== null && (
                    <div className="flex justify-between gap-3">
                      <span className="text-default-500 text-sm">Your rate</span>
                      <strong className="text-default-900 text-end">
                        {money(r.current_bill_rate)} → {perHour(r.current_bill_rate + r.increase)}
                      </strong>
                    </div>
                  )}
                  {r.can_change && (
                    <div className="mt-1.5 flex justify-end gap-2">
                      <button className="btn btn-sm btn-light px-3 font-semibold" onClick={() => setEditing(r)}>
                        Edit
                      </button>
                      <button className="btn btn-sm border-danger/35 text-danger hover:bg-danger border px-3 font-semibold hover:text-white" onClick={() => cancel(r)}>
                        Cancel request…
                      </button>
                    </div>
                  )}
                </div>
              </div>
            ))
          ) : (
            <p className="text-default-500 py-5">Nothing waiting.{canCreate && ' Use Request a pay increase when someone has earned one.'}</p>
          )}
        </div>
      </section>

      <section className="card" aria-labelledby="decided-title">
        <div className="card-header">
          <h4 id="decided-title" className="card-title">
            Decided
          </h4>
        </div>
        {decided.length ? (
          <div className="relative overflow-x-auto">
            <table className="w-full min-w-[760px] text-sm">
              <thead>
                <tr className="bg-light/25 text-default-500 text-2xs uppercase">
                  <th className="py-2.5 ps-6 pe-3 text-start font-medium">Contractor</th>
                  <th className="px-3 py-2.5 text-start font-medium">Asked</th>
                  <th className="px-3 py-2.5 text-end font-medium">Increase</th>
                  <th className="px-3 py-2.5 text-end font-medium">Your rate</th>
                  <th className="py-2.5 ps-3 pe-6 text-start font-medium">Outcome</th>
                </tr>
              </thead>
              <tbody>
                {decided.map((r) => {
                  const status = STATUS[r.status] ?? { label: r.status, tone: 'muted' as Tone }
                  const after = r.approved_bill_rate ?? (r.current_bill_rate !== null ? r.current_bill_rate + r.increase : null)
                  return (
                    <tr key={r.id} className="border-default-300 border-t align-top">
                      <td className="py-3 ps-6 pe-3">
                        <span className="text-default-900 font-semibold">{r.contractor ?? '—'}</span>
                        <span className="text-default-500 block text-xs">
                          {r.position}
                          {many && r.property && ` · ${r.property}`}
                        </span>
                      </td>
                      <td className="px-3 py-3 whitespace-nowrap">{r.created_at}</td>
                      <td className="px-3 py-3 text-end whitespace-nowrap tabular-nums">+{perHour(r.increase)}</td>
                      <td className="px-3 py-3 text-end whitespace-nowrap tabular-nums">
                        {r.status === 'completed' && r.current_bill_rate !== null && after !== null ? `${money(r.current_bill_rate)} → ${perHour(after)}` : '—'}
                      </td>
                      <td className="py-3 ps-3 pe-6">
                        <Pill tone={status.tone}>{`${status.label}${r.decided_at ? ` ${r.decided_at}` : ''}`}</Pill>
                        {r.status === 'completed' && r.effective && <span className="text-default-500 mt-1 block text-xs">From the week of {r.effective}</span>}
                        {r.decision_note && <span className="text-default-500 mt-1 block text-xs">{r.decision_note}</span>}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="card-body text-default-500">Approved requests show the new rate and the week it starts; declined ones show your recruiter's note.</p>
        )}
      </section>

      {creating && <RequestPanel workOrders={workOrders} preselect={preselect} waiting={open} onClose={() => setCreating(false)} />}
      {editing && <EditPanel request={editing} onClose={() => setEditing(null)} />}
    </>
  )
}

/** The hotel's side only: what it pays QCP per hour now, and after the increase. */
const RateAfter = ({ current, increaseCents }: { current: number; increaseCents: number }) => (
  <div className="bg-light rounded-lg px-3 py-2">
    <div className="text-default-500 text-xs">Your rate</div>
    <div className="text-default-900 font-semibold tabular-nums">
      {money(current)}
      {increaseCents > 0 && ` → ${money(current + increaseCents)}`} / h
    </div>
  </div>
)

const RequestPanel = ({ workOrders, preselect, waiting, onClose }: { workOrders: WoOption[]; preselect: number | null; waiting: Req[]; onClose: () => void }) => {
  const initial = workOrders.find((w) => w.id === preselect)?.id ?? workOrders[0]?.id ?? ''
  const { data, setData, post, processing, errors } = useForm<{ work_order_id: number | string; increase_amount: string; reason: string }>({
    work_order_id: initial,
    increase_amount: '',
    reason: '',
  })
  const selected = workOrders.find((w) => String(w.id) === String(data.work_order_id))
  const increaseCents = Math.round(Number(data.increase_amount) * 100) || 0
  const alreadyAsked = waiting.find((r) => String(r.work_order_id) === String(data.work_order_id))

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/pay-increases', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <SidePanel
      title="Request a pay increase"
      subtitle="Your recruiter reviews it and sets the final rate"
      onClose={onClose}
      footer={
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4" onClick={onClose}>
            Cancel
          </button>
          <button type="submit" form="pay-increase" className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white" disabled={processing}>
            Send request
          </button>
        </div>
      }>
      <form id="pay-increase" onSubmit={submit} className="space-y-4">
        <div>
          <label className="form-label" htmlFor="pi-who">
            Contractor
          </label>
          <select id="pi-who" className="form-select" value={data.work_order_id} onChange={(e) => setData('work_order_id', e.target.value)} required>
            {workOrders.map((w) => (
              <option key={w.id} value={w.id}>
                {w.label}
              </option>
            ))}
          </select>
          {errors.work_order_id && <p className="text-danger mt-1 text-sm">{errors.work_order_id}</p>}
          {alreadyAsked && (
            <p className="bg-warning/15 text-default-900 mt-2 rounded-lg px-3 py-2 text-sm">
              You already asked for +{perHour(alreadyAsked.increase)} on {alreadyAsked.created_at}. Edit that request instead of sending a second one.
            </p>
          )}
        </div>
        <div className="grid grid-cols-2 items-end gap-3">
          <div>
            <label className="form-label" htmlFor="pi-amount">
              Increase per hour ($)
            </label>
            <input
              id="pi-amount"
              type="number"
              step="0.01"
              min="0.01"
              className="form-input"
              placeholder="1.00"
              value={data.increase_amount}
              onChange={(e) => setData('increase_amount', e.target.value)}
              required
              autoFocus={preselect !== null}
            />
          </div>
          {selected && <RateAfter current={selected.bill_rate} increaseCents={increaseCents} />}
        </div>
        {errors.increase_amount && <p className="text-danger -mt-2 text-sm">{errors.increase_amount}</p>}
        <div>
          <label className="form-label" htmlFor="pi-reason">
            Why
          </label>
          <textarea id="pi-reason" className="form-textarea" rows={3} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
          {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
        </div>
      </form>
    </SidePanel>
  )
}

/** Change a waiting request's amount or reason; the contractor stays the same. */
const EditPanel = ({ request, onClose }: { request: Req; onClose: () => void }) => {
  const { data, setData, patch, processing, errors } = useForm({
    increase_amount: (request.increase / 100).toFixed(2),
    reason: request.reason ?? '',
  })
  const increaseCents = Math.round(Number(data.increase_amount) * 100) || 0

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch(`/pay-increases/${request.id}`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <SidePanel
      title={`Edit request · ${request.contractor ?? 'contractor'}`}
      subtitle={[request.position, request.property].filter(Boolean).join(' · ')}
      onClose={onClose}
      footer={
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4" onClick={onClose}>
            Close
          </button>
          <button type="submit" form="pay-increase-edit" className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white" disabled={processing}>
            Save changes
          </button>
        </div>
      }>
      <form id="pay-increase-edit" onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 items-end gap-3">
          <div>
            <label className="form-label" htmlFor="pe-amount">
              Increase per hour ($)
            </label>
            <input id="pe-amount" type="number" step="0.01" min="0.01" className="form-input" value={data.increase_amount} onChange={(e) => setData('increase_amount', e.target.value)} required />
          </div>
          {request.current_bill_rate !== null && <RateAfter current={request.current_bill_rate} increaseCents={increaseCents} />}
        </div>
        {errors.increase_amount && <p className="text-danger -mt-2 text-sm">{errors.increase_amount}</p>}
        <div>
          <label className="form-label" htmlFor="pe-reason">
            Why
          </label>
          <textarea id="pe-reason" className="form-textarea" rows={3} value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
          {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
        </div>
        {'request' in errors && <p className="text-danger text-sm">{(errors as Record<string, string>).request}</p>}
      </form>
    </SidePanel>
  )
}

export default Page
