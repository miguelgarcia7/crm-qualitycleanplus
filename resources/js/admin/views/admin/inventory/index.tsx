import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Variant = { id: number; label: string; sku: string | null; current_stock: number; reorder_threshold: number; status: string }
type Item = { id: number; name: string; category: string; description: string | null; variants: Variant[] }
type Category = { id: number; name: string; slug: string; has_variants: boolean }
type Props = {
  categories: Category[]
  items: Item[]
  stats: { low_stock: number; out_of_stock: number }
  can: { create: boolean; receive: boolean; manual_out: boolean; return: boolean }
}

const statusBadge = (s: string) =>
  s === 'in_stock' ? 'bg-success/15 text-success'
    : s === 'low_stock' ? 'bg-warning/15 text-warning'
      : s === 'out_of_stock' ? 'bg-danger/15 text-danger' : 'bg-secondary/15 text-secondary'
const statusLabel = (s: string) => ({ in_stock: 'In Stock', low_stock: 'Low', out_of_stock: 'Out', not_tracked: 'Untracked' }[s] ?? s)

type MoveMode = { variant: Variant; mode: 'receive' | 'manual-out' | 'return' } | null

const Page = ({ categories, items, stats, can }: Props) => {
  const [newItem, setNewItem] = useState(false)
  const [move, setMove] = useState<MoveMode>(null)

  return (
    <>
      <Head title="Inventory" />
      <PageBreadcrumb title="Inventory" subtitle="Browse Items" />

      <div className="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="card"><div className="card-body p-5"><p className="text-default-400 text-sm">Low stock</p><h3 className="text-warning text-2xl font-bold">{stats.low_stock}</h3></div></div>
        <div className="card"><div className="card-body p-5"><p className="text-default-400 text-sm">Out of stock</p><h3 className="text-danger text-2xl font-bold">{stats.out_of_stock}</h3></div></div>
      </div>

      <div className="card">
        <div className="card-header">
          <div className="flex items-center gap-4">
            <h4 className="card-title">Items</h4>
            <Link href="/admin/inventory/purchase-orders" className="text-default-500 text-sm hover:underline">Purchase Orders</Link>
            <Link href="/admin/inventory/equipment" className="text-default-500 text-sm hover:underline">Equipment</Link>
          </div>
          {can.create && <button className="btn bg-primary hover:bg-primary-hover px-4 py-1.5 font-semibold text-white" onClick={() => setNewItem(true)}>+ New Item</button>}
        </div>
        <div className="table-wrapper">
          <table className="table table-hover text-sm">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-2xs uppercase">
                <th>Item</th>
                <th>Variant</th>
                <th className="text-end">On hand</th>
                <th>Status</th>
                <th className="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              {items.length ? (
                items.flatMap((item) =>
                  item.variants.map((v, idx) => (
                    <tr key={v.id}>
                      <td>{idx === 0 ? <div><div className="font-medium">{item.name}</div><div className="text-default-400 text-xs">{item.category}</div></div> : ''}</td>
                      <td>{v.label}{v.sku && <span className="text-default-400 text-xs"> · {v.sku}</span>}</td>
                      <td className="text-end">{v.current_stock}</td>
                      <td><span className={`badge badge-label ${statusBadge(v.status)}`}>{statusLabel(v.status)}</span></td>
                      <td className="text-end whitespace-nowrap">
                        {can.receive && <button className="text-primary text-xs hover:underline" onClick={() => setMove({ variant: v, mode: 'receive' })}>receive</button>}
                        {can.manual_out && <button className="text-default-500 ms-3 text-xs hover:underline" onClick={() => setMove({ variant: v, mode: 'manual-out' })}>take out</button>}
                        {can.return && <button className="text-default-500 ms-3 text-xs hover:underline" onClick={() => setMove({ variant: v, mode: 'return' })}>return</button>}
                      </td>
                    </tr>
                  )),
                )
              ) : (
                <tr><td colSpan={5} className="text-default-400 py-4 text-center">No items yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {newItem && <NewItemModal categories={categories} onClose={() => setNewItem(false)} />}
      {move && <MovementModal variant={move.variant} mode={move.mode} onClose={() => setMove(null)} />}
    </>
  )
}

const NewItemModal = ({ categories, onClose }: { categories: Category[]; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm<{
    name: string; category_id: number | string; description: string; has_variants: boolean; reorder_threshold: number
    variants: { size: string; color: string; sku: string; reorder_threshold: number }[]
  }>({
    name: '', category_id: categories[0]?.id ?? '', description: '',
    has_variants: categories[0]?.has_variants ?? false, reorder_threshold: 0, variants: [],
  })

  const onCategory = (id: string) => {
    const cat = categories.find((c) => c.id === Number(id))
    setData((d) => ({ ...d, category_id: id, has_variants: cat?.has_variants ?? false }))
  }
  const addVariant = () => setData('variants', [...data.variants, { size: '', color: '', sku: '', reorder_threshold: 0 }])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/inventory/items', { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">New Item</h4></div>
        <div className="card-body max-h-[70vh] overflow-y-auto p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Name</label>
              <input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
              {errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}
            </div>
            <div>
              <label className="form-label">Category</label>
              <select className="form-select" value={data.category_id} onChange={(e) => onCategory(e.target.value)}>
                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </div>
            <div>
              <label className="form-label">Description</label>
              <input className="form-input" value={data.description} onChange={(e) => setData('description', e.target.value)} />
            </div>
            {data.has_variants ? (
              <div>
                <div className="mb-2 flex items-center justify-between">
                  <label className="form-label mb-0">Variants</label>
                  <button type="button" className="text-primary text-sm" onClick={addVariant}>+ add variant</button>
                </div>
                {data.variants.map((v, i) => (
                  <div key={i} className="mb-2 grid grid-cols-4 gap-2">
                    <input className="form-input" placeholder="Size" value={v.size} onChange={(e) => { const n = [...data.variants]; n[i].size = e.target.value; setData('variants', n) }} />
                    <input className="form-input" placeholder="Color" value={v.color} onChange={(e) => { const n = [...data.variants]; n[i].color = e.target.value; setData('variants', n) }} />
                    <input className="form-input" placeholder="SKU" value={v.sku} onChange={(e) => { const n = [...data.variants]; n[i].sku = e.target.value; setData('variants', n) }} />
                    <input type="number" min="0" className="form-input" placeholder="Reorder" value={v.reorder_threshold} onChange={(e) => { const n = [...data.variants]; n[i].reorder_threshold = Number(e.target.value); setData('variants', n) }} />
                  </div>
                ))}
                {data.variants.length === 0 && <p className="text-default-400 text-sm">Add at least one size/color variant.</p>}
              </div>
            ) : (
              <div>
                <label className="form-label">Reorder threshold (0 = not tracked)</label>
                <input type="number" min="0" className="form-input" value={data.reorder_threshold} onChange={(e) => setData('reorder_threshold', Number(e.target.value))} />
              </div>
            )}
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Create</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

const MovementModal = ({ variant, mode, onClose }: { variant: Variant; mode: 'receive' | 'manual-out' | 'return'; onClose: () => void }) => {
  const title = mode === 'receive' ? 'Receive Stock' : mode === 'manual-out' ? 'Manual Stock Out' : 'Return to Stock'
  const { data, setData, post, processing, errors } = useForm({ quantity: 1, reason: '' })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/inventory/variants/${variant.id}/${mode}`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">{title}</h4><p className="text-default-400 text-sm">{variant.label} — {variant.current_stock} on hand</p></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Quantity</label>
              <input type="number" min="1" className="form-input" value={data.quantity} onChange={(e) => setData('quantity', Number(e.target.value))} required />
              {errors.quantity && <p className="text-danger mt-1 text-sm">{errors.quantity}</p>}
            </div>
            <div>
              <label className="form-label">Reason</label>
              <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
              {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
