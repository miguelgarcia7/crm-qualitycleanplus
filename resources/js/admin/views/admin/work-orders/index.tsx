import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type WorkOrderRow = {
  id: number
  contractor: string | null
  property: string | null
  position: string | null
  pay_rate: number
  bill_rate: number
  status: string
  start_date: string
}

type Props = {
  workOrders: WorkOrderRow[]
  can: { create: boolean }
}

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`

const statusClass = (s: string) =>
  s === 'active' ? 'badge-soft-success' : s === 'closed' ? 'badge-soft-secondary' : 'badge-soft-warning'

const Page = ({ workOrders, can }: Props) => (
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
              <th></th>
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
                  </td>
                  <td className="text-end">
                    <Link href={`/admin/work-orders/${w.id}/edit`} className="text-primary text-sm hover:underline">
                      Edit
                    </Link>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={8} className="text-default-400 py-4 text-center">
                  No work orders yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  </>
)

export default Page
