import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type Row = {
  id: number
  invoice_number: string
  property: string | null
  issue_date: string
  due_date: string
  total: number
  status: string
  status_label: string
}

type Props = { invoices: Row[] }

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`

const statusClass = (status: string) =>
  status === 'invoice_sent'
    ? 'bg-success/15 text-success'
    : status === 'voided'
      ? 'bg-danger/15 text-danger'
      : 'bg-warning/15 text-warning'

const Page = ({ invoices }: Props) => (
  <>
    <Head title="Invoices" />
    <PageBreadcrumb title="Invoices" subtitle="Billing" />

    <div className="card">
      <div className="card-header">
        <h4 className="card-title">
          Invoices
          {invoices.length > 0 && <span className="text-default-400 ms-1 text-sm font-normal">({invoices.length})</span>}
        </h4>
      </div>

      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-2xs uppercase">
              <th>Invoice</th>
              <th>Property</th>
              <th>Issued</th>
              <th>Due</th>
              <th className="text-end">Total</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {invoices.length ? (
              invoices.map((invoice) => (
                <tr key={invoice.id}>
                  <td className="font-medium">
                    <Link href={`/invoices/${invoice.id}`} className="hover:text-primary">
                      {invoice.invoice_number}
                    </Link>
                  </td>
                  <td>{invoice.property}</td>
                  <td>{invoice.issue_date}</td>
                  <td>{invoice.due_date}</td>
                  <td className="text-end">{money(invoice.total)}</td>
                  <td>
                    <span className={`badge badge-label ${statusClass(invoice.status)}`}>{invoice.status_label}</span>
                  </td>
                  <td className="text-end">
                    <Link href={`/invoices/${invoice.id}`} className="text-primary text-sm hover:underline">
                      View
                    </Link>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={7} className="text-default-400 py-4 text-center">
                  No invoices yet.
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
