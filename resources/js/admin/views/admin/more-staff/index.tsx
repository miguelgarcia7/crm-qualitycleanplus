import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router } from '@inertiajs/react'

type Req = {
  id: number; property: string; position: string; quantity_requested: number
  quantity_fulfilled: number; by_date: string; urgency: string; status: string
  reason: string; requested_by: string | null; is_overdue: boolean
}
type Props = { requests: Req[]; can: { decline: boolean; cancel: boolean } }

const urgencyBadge = (u: string) =>
  u === 'urgent' ? 'badge-soft-danger' : u === 'high' ? 'badge-soft-warning' : u === 'normal' ? 'badge-soft-primary' : 'badge-soft-secondary'

const Page = ({ requests, can }: Props) => (
  <>
    <Head title="Staffing Requests" />
    <PageBreadcrumb title="Staffing Requests" subtitle="Recruiting" />

    <div className="card rounded-2xl">
      <div className="card-header flex items-center justify-between p-6">
        <h4 className="card-title">Open Requests</h4>
        <Link href="/admin/work-orders/create" className="btn btn-soft-primary px-4 py-1.5">+ Place a contractor</Link>
      </div>
      <div className="table-wrapper">
        <table className="table table-hover text-sm">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-2xs uppercase">
              <th>Property</th><th>Position</th><th>Urgency</th><th className="text-center">Progress</th><th>By date</th><th>Requested by</th><th className="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            {requests.length ? requests.map((r) => (
              <tr key={r.id}>
                <td className="font-medium">{r.property}</td>
                <td>{r.position}<div className="text-default-400 text-xs">{r.reason}</div></td>
                <td><span className={`badge ${urgencyBadge(r.urgency)}`}>{r.urgency}</span></td>
                <td className="text-center">{r.quantity_fulfilled} / {r.quantity_requested}</td>
                <td>{r.by_date}{r.is_overdue && <span className="badge badge-soft-danger ms-2">Overdue</span>}</td>
                <td>{r.requested_by ?? '—'}</td>
                <td className="text-end whitespace-nowrap">
                  {can.decline && (
                    <button
                      className="btn btn-sm btn-soft-danger"
                      onClick={() => { const reason = window.prompt('Reason for declining?'); if (reason) router.post(`/admin/staffing-requests/${r.id}/decline`, { reason }, { preserveScroll: true }) }}
                    >Decline</button>
                  )}
                  {can.cancel && (
                    <button
                      className="btn btn-sm btn-light ms-2"
                      onClick={() => { const reason = window.prompt('Reason for cancelling?'); if (reason) router.post(`/admin/staffing-requests/${r.id}/cancel`, { reason }, { preserveScroll: true }) }}
                    >Cancel</button>
                  )}
                </td>
              </tr>
            )) : <tr><td colSpan={7} className="text-default-400 py-4 text-center">No open staffing requests.</td></tr>}
          </tbody>
        </table>
      </div>
      <div className="text-default-400 px-6 py-4 text-xs">
        Fulfill a request by creating a work order at the property and linking it to the request.
      </div>
    </div>
  </>
)

export default Page
