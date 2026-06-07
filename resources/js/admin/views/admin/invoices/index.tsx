import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type Row = { id: number; invoice_number: string; property: string | null; issue_date: string; total: number; status: string; status_label: string }
type Props = { invoices: Row[] }

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const statusClass = (s: string) =>
  s === 'invoice_sent' ? 'badge-soft-success' : s === 'invoiced' ? 'badge-soft-primary' : s === 'voided' ? 'badge-soft-danger' : 'badge-soft-secondary'

const Page = ({ invoices }: Props) => (
  <>
    <Head title="Invoices" />
    <PageBreadcrumb title="Invoices" subtitle="Billing" />

    <div className="card rounded-2xl">
      <div className="card-header p-6"><h4 className="card-title">Invoices</h4></div>
      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-2xs uppercase">
              <th>Number</th>
              <th>Property</th>
              <th>Issued</th>
              <th className="text-end">Total</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {invoices.length ? (
              invoices.map((i) => (
                <tr key={i.id}>
                  <td>
                    <Link href={`/admin/invoices/${i.id}`} className="text-primary font-medium hover:underline">{i.invoice_number}</Link>
                  </td>
                  <td>{i.property}</td>
                  <td>{i.issue_date}</td>
                  <td className="text-end">{money(i.total)}</td>
                  <td><span className={`badge ${statusClass(i.status)}`}>{i.status_label}</span></td>
                </tr>
              ))
            ) : (
              <tr><td colSpan={5} className="text-default-400 py-4 text-center">No invoices yet.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  </>
)

export default Page
