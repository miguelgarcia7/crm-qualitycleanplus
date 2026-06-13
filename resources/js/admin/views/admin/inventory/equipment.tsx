import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router } from '@inertiajs/react'

type Assignment = { id: number; person: string; item: string; quantity: number; assigned_at: string | null }
type Props = { assignments: Assignment[]; can: { return: boolean } }

const Page = ({ assignments, can }: Props) => {
  const resolve = (a: Assignment, returned: boolean) => {
    const notes = returned ? '' : window.prompt('Notes (item not returned / lost)?') ?? ''
    router.post(`/admin/inventory/equipment/${a.id}/return`, { returned, notes }, { preserveScroll: true })
  }

  return (
    <>
      <Head title="Equipment" />
      <PageBreadcrumb title="Equipment" subtitle="Inventory" />

      <div className="card">
        <div className="card-header">
          <div className="flex items-center gap-4">
            <h4 className="card-title">Assigned Equipment</h4>
            <Link href="/admin/inventory" className="text-default-500 text-sm hover:underline">← Inventory</Link>
          </div>
        </div>
        <div className="table-wrapper">
          <table className="table table-hover text-sm">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase"><th>Person</th><th>Item</th><th className="text-end">Qty</th><th>Assigned</th><th className="text-end">Action</th></tr>
            </thead>
            <tbody>
              {assignments.length ? assignments.map((a) => (
                <tr key={a.id}>
                  <td className="font-medium">{a.person}</td>
                  <td>{a.item}</td>
                  <td className="text-end">{a.quantity}</td>
                  <td>{a.assigned_at}</td>
                  <td className="text-end whitespace-nowrap">
                    {can.return && (
                      <div className="inline-flex gap-2">
                        <button className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => resolve(a, true)}>Returned</button>
                        <button className="btn bg-danger/15 text-danger hover:bg-danger hover:text-white" onClick={() => resolve(a, false)}>Lost</button>
                      </div>
                    )}
                  </td>
                </tr>
              )) : <tr><td colSpan={5} className="text-default-400 py-4 text-center">No assigned equipment.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}

export default Page
