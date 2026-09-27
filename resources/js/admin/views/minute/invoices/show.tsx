import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'

type Item = {
  contractor_name: string
  position_name: string
  job_code: string | null
  regular_minutes: number
  overtime_minutes: number
  bill_rate: number
  ot_bill_rate: number
  total_bill: number
}

type PositionSummary = {
  position: string
  job_code: string | null
  /** null when the position's contractors were billed at different rates. */
  bill_rate: number | null
  regular_minutes: number
  overtime_minutes: number
  holiday_minutes: number
  training_minutes: number
  total_minutes: number
  total_bill: number
}

type Invoice = {
  id: number
  invoice_number: string
  issue_date: string
  due_date: string
  status: string
  status_label: string
  property_snapshot: Record<string, string | null>
  invoicer_snapshot: Record<string, string | null>
  subtotal: number
  work_subtotal: number
  adjustment_total: number
  tax_rate: number
  tax_amount: number
  total: number
  items: Item[]
  position_summary: PositionSummary[]
}

type Props = { invoice: Invoice; previewNotice: PreviewNotice }

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const hrs = (minutes: number) => (minutes / 60).toFixed(2)

const statusClass = (status: string) =>
  status === 'invoice_sent' ? 'bg-success/15 text-success' : status === 'voided' ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning'

/** One rate for the position, or a flag that its contractors were billed differently. */
const RateCell = ({ rate }: { rate: number | null }) =>
  rate === null ? (
    <span className="badge badge-label bg-warning/15 text-warning">Mixed</span>
  ) : (
    <>{money(rate)}</>
  )

type PreviewNotice = { heading: string; body: string } | null

/**
 * These documents are a working record — the billable invoice is issued
 * elsewhere. Saying so on the artefact itself is the only place a client
 * reads it, so it sits beside the totals rather than in a help page.
 */
const PreviewOnlyNotice = ({ notice }: { notice: PreviewNotice }) =>
  notice === null ? null : (
    <div className="border-danger/40 bg-danger/5 flex items-start gap-3 rounded-lg border p-4" role="note">
      <Icon icon="alert-circle" className="text-danger mt-0.5 size-5 shrink-0" />
      <span className="text-sm">
        <span className="text-danger block font-semibold">{notice.heading}</span>
        <span className="text-danger/80 block">{notice.body}</span>
      </span>
    </div>
  )

const Page = ({ invoice, previewNotice }: Props) => (
  <>
    <Head title={invoice.invoice_number} />
    <PageBreadcrumb title={invoice.invoice_number} subtitle="Invoice" />

    <div className="card">
      <div className="card-header">
        <div className="flex items-center gap-3">
          <h4 className="card-title">{invoice.invoice_number}</h4>
          <span className={`badge badge-label ${statusClass(invoice.status)}`}>{invoice.status_label}</span>
        </div>
        <div className="flex items-center gap-2">
          <Link href="/invoices" className="btn btn-light text-nowrap">
            <Icon icon="arrow-left" className="me-1 size-4" /> All invoices
          </Link>
          <a href={`/invoices/${invoice.id}/pdf`} className="btn btn-light px-4 py-1.5">
            Download PDF
          </a>
        </div>
      </div>

      <div className="card-body p-6">
        {invoice.status === 'voided' && (
          <div className="bg-danger/15 text-danger mb-6 rounded-md px-4 py-3 text-sm">
            This invoice has been voided and is no longer payable. A replacement may have been issued.
          </div>
        )}

        <div className="mb-6 grid grid-cols-2 gap-6">
          <div>
            <div className="text-default-400 text-xs uppercase">From</div>
            <div className="font-medium">{invoice.invoicer_snapshot.name}</div>
          </div>
          <div>
            <div className="text-default-400 text-xs uppercase">Bill To</div>
            <div className="font-medium">{invoice.property_snapshot.name}</div>
            <div className="text-default-400 text-sm">
              {invoice.property_snapshot.city} {invoice.property_snapshot.state} {invoice.property_snapshot.zip}
            </div>
          </div>
          <div>
            <span className="text-default-400">Issued:</span> {invoice.issue_date}
          </div>
          <div>
            <span className="text-default-400">Due:</span> {invoice.due_date}
          </div>
        </div>

        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Contractor</th>
                <th>Job Code</th>
                <th className="text-end">Reg Hrs | Rate</th>
                <th className="text-end">OT Hrs | Rate</th>
                <th className="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              {invoice.items.map((item, idx) => (
                <tr key={idx}>
                  <td>
                    <span className="font-medium">{item.contractor_name}</span>
                    <span className="text-default-400 block text-xs">{item.position_name}</span>
                  </td>
                  <td className="text-default-500">{item.job_code ?? '—'}</td>
                  <td className="text-end whitespace-nowrap">
                    {hrs(item.regular_minutes)} <span className="text-default-400">| {money(item.bill_rate)}</span>
                  </td>
                  <td className="text-end whitespace-nowrap">
                    {item.overtime_minutes > 0 ? (
                      <>
                        {hrs(item.overtime_minutes)} <span className="text-default-400">| {money(item.ot_bill_rate)}</span>
                      </>
                    ) : (
                      hrs(item.overtime_minutes)
                    )}
                  </td>
                  <td className="text-end">{money(item.total_bill)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {invoice.position_summary.length > 0 && (
          <div className="mt-6">
            <h5 className="mb-2 font-semibold">Position Summary</h5>
            <div className="table-wrapper">
              <table className="table">
                <thead className="thead-sm">
                  <tr className="bg-light/25 text-xs uppercase">
                    <th>Position</th>
                    <th>Job Code</th>
                    <th className="text-end">Rate</th>
                    <th className="text-end">Reg Hrs</th>
                    <th className="text-end">OT Hrs</th>
                    <th className="text-end">HLD Hrs</th>
                    <th className="text-end">Total Hrs</th>
                    <th className="text-end">Billed</th>
                  </tr>
                </thead>
                <tbody>
                  {invoice.position_summary.map((row) => (
                    <tr key={row.position}>
                      <td className="font-medium">{row.position}</td>
                      <td className="text-default-500">{row.job_code ?? '—'}</td>
                      <td className="text-end">
                        <RateCell rate={row.bill_rate} />
                      </td>
                      <td className="text-end">{hrs(row.regular_minutes)}</td>
                      <td className="text-end">{hrs(row.overtime_minutes)}</td>
                      <td className="text-end">{hrs(row.holiday_minutes)}</td>
                      <td className="text-end font-medium">{hrs(row.total_minutes)}</td>
                      <td className="text-end">{money(row.total_bill)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        <div className="mt-4 flex flex-wrap items-start justify-between gap-4">
          <PreviewOnlyNotice notice={previewNotice} />
          <table className="ms-auto w-64 text-sm">
            <tbody>
              <tr>
                <td className="py-1">Work Subtotal</td>
                <td className="py-1 text-end">{money(invoice.work_subtotal)}</td>
              </tr>
              <tr>
                <td className="py-1">Adjustments</td>
                {/* Signed: a credit reads as a credit rather than as a smaller bill. */}
                <td className={cn('py-1 text-end', invoice.adjustment_total < 0 ? 'text-danger' : 'text-success')}>
                  {invoice.adjustment_total < 0 ? '-' : '+'}
                  {money(Math.abs(invoice.adjustment_total))}
                </td>
              </tr>
              <tr>
                <td className="py-1">Subtotal</td>
                <td className="py-1 text-end">{money(invoice.subtotal)}</td>
              </tr>
              <tr>
                <td className="py-1">Tax Rate</td>
                {/* Shown separately: a bare $0.00 cannot say whether the rate is
                    zero or the calculation went wrong. */}
                <td className="text-default-500 py-1 text-end">{(invoice.tax_rate * 100).toFixed(2).replace(/\.00$/, '')}%</td>
              </tr>
              <tr>
                <td className="py-1">Total Tax</td>
                <td className="py-1 text-end">{money(invoice.tax_amount)}</td>
              </tr>
              <tr className="border-default-300 border-t font-bold">
                <td className="py-1">Total</td>
                <td className="py-1 text-end">{money(invoice.total)}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </>
)

export default Page
