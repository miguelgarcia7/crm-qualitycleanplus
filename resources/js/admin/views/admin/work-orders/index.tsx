import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type WorkOrderRow = {
  id: number
  person_id: number
  contractor: string | null
  property_id: number
  property: string | null
  position_id: number
  position: string | null
  pay_rate: number
  bill_rate: number
  ot_pay_rate: number
  ot_bill_rate: number
  status: string
  is_temporary_assignment: boolean
  start_date: string
}
type Option = { id: number; name: string }
type Catalogs = { properties: Option[]; positions: Option[]; recruiters: Option[] }
type Props = {
  workOrders: WorkOrderRow[]
  catalogs: Catalogs
  can: { create: boolean; transfer: boolean; temp: boolean }
}

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const statusClass = (s: string) => (s === 'active' ? 'badge-soft-success' : s === 'closed' ? 'badge-soft-secondary' : 'badge-soft-warning')

type ModalState = { kind: 'transfer' | 'temp'; wo: WorkOrderRow } | null

const Page = ({ workOrders, catalogs, can }: Props) => {
  const [modal, setModal] = useState<ModalState>(null)

  return (
    <>
      <Head title="Work Orders" />
      <PageBreadcrumb title="Work Orders" subtitle="Operations" />

      <div className="card rounded-2xl">
        <div className="card-header flex items-center justify-between p-6">
          <h4 className="card-title">Work Orders</h4>
          {can.create && (
            <Link href="/admin/work-orders/create" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white">
              Add Work Order
            </Link>
          )}
        </div>

        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-2xs uppercase">
                <th>Contractor</th>
                <th>Property</th>
                <th>Position</th>
                <th>Pay</th>
                <th>Bill</th>
                <th>Start</th>
                <th>Status</th>
                <th className="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              {workOrders.length ? (
                workOrders.map((w) => (
                  <tr key={w.id}>
                    <td className="font-medium">{w.contractor}</td>
                    <td>{w.property}</td>
                    <td>{w.position}</td>
                    <td>{money(w.pay_rate)}</td>
                    <td>{money(w.bill_rate)}</td>
                    <td>{w.start_date}</td>
                    <td>
                      <span className={`badge ${statusClass(w.status)} capitalize`}>{w.status}</span>
                      {w.is_temporary_assignment && <span className="badge badge-soft-info ms-1">temp</span>}
                    </td>
                    <td className="text-end whitespace-nowrap">
                      <Link href={`/admin/work-orders/${w.id}/edit`} className="text-primary text-sm hover:underline">Edit</Link>
                      {w.status === 'active' && can.transfer && (
                        <button className="text-default-500 ms-3 text-sm hover:underline" onClick={() => setModal({ kind: 'transfer', wo: w })}>Transfer</button>
                      )}
                      {w.status === 'active' && can.temp && !w.is_temporary_assignment && (
                        <button className="text-default-500 ms-3 text-sm hover:underline" onClick={() => setModal({ kind: 'temp', wo: w })}>Temp</button>
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={8} className="text-default-400 py-4 text-center">No work orders yet.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {modal?.kind === 'transfer' && <TransferModal wo={modal.wo} catalogs={catalogs} onClose={() => setModal(null)} />}
      {modal?.kind === 'temp' && <TempModal wo={modal.wo} catalogs={catalogs} onClose={() => setModal(null)} />}
    </>
  )
}

// Fetch the Bible rate for a (property, position) and fill the form's rate fields.
const useRateLookup = (setData: (key: string, value: string) => void) => {
  return (propertyId: number | string, positionId: number | string) => {
    if (!propertyId || !positionId) return
    fetch(`/admin/work-orders/rate-lookup?property_id=${propertyId}&position_id=${positionId}`, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : null))
      .then((rate) => {
        if (!rate) return
        setData('pay_rate', String(rate.pay_rate / 100))
        setData('bill_rate', String(rate.bill_rate / 100))
        setData('ot_pay_rate', String(rate.ot_pay_rate / 100))
        setData('ot_bill_rate', String(rate.ot_bill_rate / 100))
      })
      .catch(() => undefined)
  }
}

const RateFields = ({ data, setData }: { data: any; setData: (k: any, v: any) => void }) => (
  <div className="grid grid-cols-2 gap-3">
    {(['pay_rate', 'bill_rate', 'ot_pay_rate', 'ot_bill_rate'] as const).map((k) => (
      <div key={k}>
        <label className="form-label capitalize">{k.replaceAll('_', ' ')} ($/hr)</label>
        <input type="number" step="0.01" min="0" className="form-input" value={data[k]} onChange={(e) => setData(k, e.target.value)} required />
      </div>
    ))}
  </div>
)

const Shell = ({ title, subtitle, onClose, children }: { title: string; subtitle: string; onClose: () => void; children: React.ReactNode }) => (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
    <div className="card w-full max-w-lg rounded-2xl" onClick={(e) => e.stopPropagation()}>
      <div className="card-header p-5"><h4 className="card-title">{title}</h4><p className="text-default-400 text-sm">{subtitle}</p></div>
      <div className="card-body max-h-[75vh] overflow-y-auto p-5">{children}</div>
    </div>
  </div>
)

const TransferModal = ({ wo, catalogs, onClose }: { wo: WorkOrderRow; catalogs: Catalogs; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({
    effective_date: new Date().toISOString().slice(0, 10),
    new_property_id: wo.property_id as number | string,
    new_position_id: wo.position_id as number | string,
    new_recruiter_id: '' as number | string,
    pay_rate: String(wo.pay_rate / 100),
    bill_rate: String(wo.bill_rate / 100),
    ot_pay_rate: String(wo.ot_pay_rate / 100),
    ot_bill_rate: String(wo.ot_bill_rate / 100),
    reason: '',
    notes: '',
  })
  const lookup = useRateLookup(setData as any)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/work-orders/${wo.id}/transfer`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <Shell title="Transfer / Position Change" subtitle={`${wo.contractor} — currently ${wo.property}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">New property</label>
            <select className="form-select" value={data.new_property_id} onChange={(e) => { setData('new_property_id', e.target.value); lookup(e.target.value, data.new_position_id) }}>
              {catalogs.properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
          <div>
            <label className="form-label">New position</label>
            <select className="form-select" value={data.new_position_id} onChange={(e) => { setData('new_position_id', e.target.value); lookup(data.new_property_id, e.target.value) }}>
              {catalogs.positions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">Effective date</label>
            <input type="date" className="form-input" value={data.effective_date} onChange={(e) => setData('effective_date', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">New recruiter (optional)</label>
            <select className="form-select" value={data.new_recruiter_id} onChange={(e) => setData('new_recruiter_id', e.target.value)}>
              <option value="">— keep current —</option>
              {catalogs.recruiters.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>
          </div>
        </div>
        <RateFields data={data} setData={setData} />
        <div>
          <label className="form-label">Reason</label>
          <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
          {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
        </div>
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn bg-primary px-4 py-2 font-semibold text-white" disabled={processing}>Apply Transfer</button>
        </div>
      </form>
    </Shell>
  )
}

const TempModal = ({ wo, catalogs, onClose }: { wo: WorkOrderRow; catalogs: Catalogs; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({
    new_property_id: '' as number | string,
    home_property_id: wo.property_id,
    position_id: wo.position_id as number | string,
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    pay_rate: String(wo.pay_rate / 100),
    bill_rate: String(wo.bill_rate / 100),
    ot_pay_rate: String(wo.ot_pay_rate / 100),
    ot_bill_rate: String(wo.ot_bill_rate / 100),
    reason: '',
  })
  const lookup = useRateLookup(setData as any)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/work-orders/${wo.id}/temporary-assignment`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <Shell title="Temporary Assignment" subtitle={`${wo.contractor} — home: ${wo.property}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">Host property</label>
            <select className="form-select" value={data.new_property_id} onChange={(e) => { setData('new_property_id', e.target.value); lookup(e.target.value, data.position_id) }} required>
              <option value="">Select…</option>
              {catalogs.properties.filter((p) => p.id !== wo.property_id).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
            {errors.new_property_id && <p className="text-danger mt-1 text-sm">{errors.new_property_id}</p>}
          </div>
          <div>
            <label className="form-label">Position</label>
            <select className="form-select" value={data.position_id} onChange={(e) => { setData('position_id', e.target.value); lookup(data.new_property_id, e.target.value) }}>
              {catalogs.positions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">Start date</label>
            <input type="date" className="form-input" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">End date</label>
            <input type="date" className="form-input" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} required />
            {errors.end_date && <p className="text-danger mt-1 text-sm">{errors.end_date}</p>}
          </div>
        </div>
        <RateFields data={data} setData={setData} />
        <div>
          <label className="form-label">Reason</label>
          <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
        </div>
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn bg-primary px-4 py-2 font-semibold text-white" disabled={processing}>Create Temp Assignment</button>
        </div>
      </form>
    </Shell>
  )
}

export default Page
