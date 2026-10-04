import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, router } from '@inertiajs/react'

type Assignment = { id: number; person: string; item: string; quantity: number; assigned_at: string | null }
type Props = { assignments: Assignment[]; can: { return: boolean } }

const Page = ({ assignments, can }: Props) => {
  const resolve = (a: Assignment, returned: boolean) => {
    const post = (notes: string) => router.post(`/admin/inventory/equipment/${a.id}/return`, { returned, notes }, { preserveScroll: true })
    if (returned) return post('')

    confirmAction({
      title: 'Mark not returned',
      message: <><strong>{a.person}</strong> did not return <strong>{a.quantity} × {a.item}</strong>.</>,
      input: { label: 'Notes', placeholder: 'Lost, damaged, kept…' },
      confirmLabel: 'Mark not returned',
      onConfirm: post,
    })
  }

  return (
    <>
      <Head title="Equipment" />
      <PageBreadcrumb title="Equipment" subtitle="Inventory" />

      <div className="card">
        <div className="card-header">
          <h4 className="card-title">Assigned Equipment</h4>
          <Link href="/admin/inventory" className="btn btn-light text-nowrap">
            <Icon icon="arrow-left" className="me-1 size-4" /> Inventory
          </Link>
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
                        <button className="btn bg-info/15 text-info hover:bg-info hover:text-white" onClick={() => resolve(a, true)}>Returned</button>
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
