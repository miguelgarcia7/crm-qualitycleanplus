import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type Row = { id: number; property: string | null; week_start: string | null; status: string; status_label: string }

type Props = { timesheets: Row[] }

const statusClass = (s: string) =>
  s === 'pending_approval' ? 'badge-soft-warning' : s === 'approved' || s === 'invoiced' || s === 'invoice_sent' ? 'badge-soft-success' : s === 'declined' ? 'badge-soft-danger' : 'badge-soft-secondary'

const Page = ({ timesheets }: Props) => (
  <>
    <Head title="Timesheets" />
    <PageBreadcrumb title="Timesheets" subtitle="Approvals" />

    <div className="card rounded-2xl">
      <div className="card-header p-6">
        <h4 className="card-title">Timesheets for Approval</h4>
      </div>
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-2xs uppercase">
              <th>Property</th>
              <th>Week</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {timesheets.length ? (
              timesheets.map((t) => (
                <tr key={t.id}>
                  <td className="font-medium">{t.property}</td>
                  <td>{t.week_start}</td>
                  <td><span className={`badge ${statusClass(t.status)}`}>{t.status_label}</span></td>
                  <td className="text-end">
                    <Link href={`/timesheets/${t.id}`} className="text-primary text-sm hover:underline">Review</Link>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={4} className="text-default-400 py-4 text-center">Nothing awaiting approval.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  </>
)

export default Page
