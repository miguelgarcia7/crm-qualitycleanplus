import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type Record = {
  id: number; workflow_id: number; person: string; effective_date: string
  type: string; reason: string; status: string; initiated_by: string | null
}
type Props = { records: Record[]; can: { initiate: boolean } }

const statusBadge = (s: string) =>
  s === 'Completed' ? 'badge-soft-success' : s === 'Cancelled' || s === 'Rejected' ? 'badge-soft-danger' : 'badge-soft-primary'

const Page = ({ records, can }: Props) => (
  <>
    <Head title="Terminations" />
    <PageBreadcrumb title="Terminations" subtitle="People" />

    <div className="card rounded-2xl">
      <div className="card-header flex items-center justify-between p-6">
        <h4 className="card-title">Termination Records</h4>
        {can.initiate && (
          <Link href="/admin/terminations/create" className="btn bg-primary px-4 py-1.5 font-semibold text-white">
            + New Termination
          </Link>
        )}
      </div>
      <div className="table-wrapper">
        <table className="table table-hover text-sm">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-2xs uppercase">
              <th>Person</th><th>Effective</th><th>Type</th><th>Reason</th><th>Initiated by</th><th>Status</th><th></th>
            </tr>
          </thead>
          <tbody>
            {records.length ? records.map((r) => (
              <tr key={r.id}>
                <td className="font-medium">{r.person}</td>
                <td>{r.effective_date}</td>
                <td>{r.type}</td>
                <td>{r.reason}</td>
                <td>{r.initiated_by ?? '—'}</td>
                <td><span className={`badge ${statusBadge(r.status)}`}>{r.status}</span></td>
                <td className="text-end">
                  <Link href={`/admin/terminations/${r.workflow_id}`} className="btn btn-sm btn-soft-primary">View</Link>
                </td>
              </tr>
            )) : <tr><td colSpan={7} className="text-default-400 py-4 text-center">No terminations yet.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  </>
)

export default Page
