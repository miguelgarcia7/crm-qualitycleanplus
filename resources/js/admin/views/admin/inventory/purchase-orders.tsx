import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Line = { id: number; variant: string; quantity: number }
type Order = { id: number; status: string; status_label: string; notes: string | null; received_at: string | null; items: Line[]; can_receive: boolean }
type VariantOption = { id: number; label: string }
type Props = { orders: Order[]; variants: VariantOption[]; can: { create: boolean; receive: boolean } }

const statusBadge = (s: string) => (s === 'received' ? 'badge-soft-success' : s === 'cancelled' ? 'badge-soft-danger' : 'badge-soft-secondary')

const Page = ({ orders, variants, can }: Props) => {
  const [create, setCreate] = useState(false)

  return (
    <>
      <Head title="Purchase Orders" />
      <PageBreadcrumb title="Purchase Orders" subtitle="Inventory" />

      <div className="card rounded-2xl">
        <div className="card-header flex items-center justify-between p-6">
          <div className="flex items-center gap-4">
            <h4 className="card-title">Purchase Orders</h4>
            <Link href="/admin/inventory" className="text-default-500 text-sm hover:underline">← Inventory</Link>
          </div>
          {can.create && variants.length > 0 && <button className="btn bg-primary px-4 py-1.5 font-semibold text-white" onClick={() => setCreate(true)}>+ New PO</button>}
        </div>
        <div className="table-wrapper">
          <table className="table table-hover text-sm">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-2xs uppercase"><th>#</th><th>Items</th><th>Status</th><th className="text-end">Action</th></tr>
            </thead>
            <tbody>
              {orders.length ? orders.map((o) => (
                <tr key={o.id}>
                  <td className="font-medium">PO-{o.id}</td>
                  <td>{o.items.map((l) => `${l.variant} ×${l.quantity}`).join(', ')}</td>
                  <td><span className={`badge ${statusBadge(o.status)}`}>{o.status_label}</span></td>
                  <td className="text-end">
                    {can.receive && o.can_receive && (
                      <button className="btn btn-sm btn-primary" onClick={() => router.post(`/admin/inventory/purchase-orders/${o.id}/receive`, {}, { preserveScroll: true })}>Receive</button>
                    )}
                  </td>
                </tr>
              )) : <tr><td colSpan={4} className="text-default-400 py-4 text-center">No purchase orders.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      {create && <CreatePoModal variants={variants} onClose={() => setCreate(false)} />}
    </>
  )
}

const CreatePoModal = ({ variants, onClose }: { variants: VariantOption[]; onClose: () => void }) => {
  const { data, setData, post, processing } = useForm<{ notes: string; items: { item_variant_id: number | string; quantity: number }[] }>({
    notes: '', items: [{ item_variant_id: variants[0]?.id ?? '', quantity: 1 }],
  })
  const addLine = () => setData('items', [...data.items, { item_variant_id: variants[0]?.id ?? '', quantity: 1 }])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/inventory/purchase-orders', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-lg rounded-2xl" onClick={(e) => e.stopPropagation()}>
        <div className="card-header p-5"><h4 className="card-title">New Purchase Order</h4></div>
        <div className="card-body max-h-[70vh] overflow-y-auto p-5">
          <form onSubmit={submit} className="space-y-4">
            {data.items.map((line, i) => (
              <div key={i} className="grid grid-cols-3 gap-2">
                <select className="form-select col-span-2" value={line.item_variant_id} onChange={(e) => { const n = [...data.items]; n[i].item_variant_id = e.target.value; setData('items', n) }}>
                  {variants.map((v) => <option key={v.id} value={v.id}>{v.label}</option>)}
                </select>
                <input type="number" min="1" className="form-input" value={line.quantity} onChange={(e) => { const n = [...data.items]; n[i].quantity = Number(e.target.value); setData('items', n) }} />
              </div>
            ))}
            <button type="button" className="text-primary text-sm" onClick={addLine}>+ add line</button>
            <div>
              <label className="form-label">Notes</label>
              <input className="form-input" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary px-4 py-2 font-semibold text-white" disabled={processing}>Create</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
