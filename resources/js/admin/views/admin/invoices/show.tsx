import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Item = {
  contractor_name: string
  position_name: string
  job_code: string | null
  regular_minutes: number
  overtime_minutes: number
  holiday_minutes: number
  training_minutes: number
  bill_rate: number
  ot_bill_rate: number
  /** Read back from the frozen amount; null when the line has none. */
  holiday_rate: number | null
  training_rate: number | null
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
  work_subtotal: number
  adjustment_total: number
  subtotal: number
  tax_rate: number
  tax_amount: number
  total: number
  notification_recipient: string | null
  notification_sent_at: string | null
  property_id: number
  week_start: string | null
  week_end: string | null
  paid_at: string | null
  paid_by: string | null
  is_overdue: boolean
  days_overdue: number | null
  items: Item[]
  position_summary: PositionSummary[]
}

type Props = { invoice: Invoice; can: { send: boolean; markPaid: boolean }; previewNotice: PreviewNotice }

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const hrs = (m: number) => (m / 60).toFixed(2)
// Noon, so no timezone offset can push the date to a neighbouring day.
const at = (iso: string) => new Date(`${iso}T12:00:00`)
const longDate = (iso: string) => at(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const shortDate = (iso: string) => at(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
/** "Sep 21 – 27", or "Sep 28 – Oct 4" across a month. */
const weekLabel = (start: string, end: string) => {
  const s = at(start)
  const e = at(end)
  const month = (d: Date) => d.toLocaleDateString('en-US', { month: 'short' })
  return s.getMonth() === e.getMonth() ? `${month(s)} ${s.getDate()} – ${e.getDate()}` : `${month(s)} ${s.getDate()} – ${month(e)} ${e.getDate()}`
}

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  invoiced: 'bg-info/15 text-info',
  invoice_sent: 'bg-success/15 text-success',
  voided: 'bg-danger/15 text-danger',
}

/** Hours, with the rate they were billed at when there are any. */
const HoursAndRate = ({ minutes, rate }: { minutes: number; rate: number | null }) =>
  minutes > 0 && rate !== null ? (
    <>
      {hrs(minutes)} <span className="text-default-400">| {money(rate)}</span>
    </>
  ) : (
    <>{hrs(minutes)}</>
  )

/** One labelled fact in the invoice's header block. */
const Fact = ({ label, children }: { label: string; children: React.ReactNode }) => (
  <div>
    <div className="text-default-400 text-xs uppercase">{label}</div>
    <div className="text-default-900 font-medium">{children}</div>
  </div>
)

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

const Page = ({ invoice, can, previewNotice }: Props) => {
  const [sending, setSending] = useState(false)
  const sent = invoice.status === 'invoice_sent'
  // Holiday and training are part of each line's amount, so they get columns
  // whenever any line has them; otherwise hours times rate never adds up.
  const withHoliday = invoice.items.some((it) => it.holiday_minutes > 0)
  const withTraining = invoice.items.some((it) => it.training_minutes > 0)

  // Confirmed both ways: recording money that never arrived is as wrong as
  // un-recording money that did.
  const confirmPaid = () =>
    confirmAction({
      title: 'Mark paid',
      message: (
        <>
          Record <strong className="text-default-900">{invoice.invoice_number}</strong> ({money(invoice.total)}) as settled by{' '}
          {invoice.property_snapshot.name}?
        </>
      ),
      confirmLabel: 'Mark paid',
      tone: 'primary',
      onConfirm: () => router.post(`/admin/invoices/${invoice.id}/paid`, {}, { preserveScroll: true }),
    })

  const confirmUnpaid = () =>
    confirmAction({
      title: 'Mark unpaid',
      message: (
        <>
          Put <strong className="text-default-900">{invoice.invoice_number}</strong> back to owing? It was recorded paid on{' '}
          {invoice.paid_at && longDate(invoice.paid_at)}
          {invoice.paid_by ? ` by ${invoice.paid_by}` : ''}.
        </>
      ),
      confirmLabel: 'Mark unpaid',
      onConfirm: () => router.delete(`/admin/invoices/${invoice.id}/paid`, { preserveScroll: true }),
    })

  return (
    <>
      <Head title={invoice.invoice_number} />
      <PageBreadcrumb title={invoice.invoice_number} subtitle="Invoices" />

      <div className="card">
        <div className="card-header">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <span className={cn('badge badge-label', statusBadge[invoice.status] ?? 'bg-secondary/15 text-secondary')}>{invoice.status_label}</span>
              {invoice.paid_at ? (
                <span className="badge badge-label bg-success/15 text-success">Paid {longDate(invoice.paid_at)}</span>
              ) : (
                invoice.is_overdue && (
                  <span className="badge badge-label bg-danger/15 text-danger">
                    Overdue{invoice.days_overdue ? ` · ${invoice.days_overdue} ${invoice.days_overdue === 1 ? 'day' : 'days'}` : ''}
                  </span>
                )
              )}
            </div>
            {invoice.notification_recipient && (
              <p className="text-default-500 mt-1.5 text-sm">
                Sent{invoice.notification_sent_at ? ` ${shortDate(invoice.notification_sent_at)}` : ''} to {invoice.notification_recipient}
              </p>
            )}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <a href={`/admin/invoices/${invoice.id}/pdf`} className="btn border-default-300 hover:border-default-400 border">
              <Icon icon="download" className="size-4" /> Download PDF
            </a>
            {can.send && (
              <button
                type="button"
                className={cn('btn', sent ? 'border-default-300 hover:border-default-400 border' : 'bg-primary hover:bg-primary-hover font-semibold text-white')}
                onClick={() => setSending(true)}>
                <Icon icon="send" className="size-4" /> {sent ? 'Resend' : 'Send to Property'}
              </button>
            )}
            {can.markPaid &&
              (invoice.paid_at ? (
                <button type="button" className="btn border-default-300 hover:border-default-400 border" onClick={confirmUnpaid}>
                  Mark unpaid
                </button>
              ) : (
                <button
                  type="button"
                  className={cn('btn', sent || !can.send ? 'bg-primary hover:bg-primary-hover font-semibold text-white' : 'border-default-300 hover:border-default-400 border')}
                  onClick={confirmPaid}>
                  <Icon icon="check" className="size-4" /> Mark paid
                </button>
              ))}
          </div>
        </div>

        <div className="card-body">
          <div className="mb-6 grid grid-cols-2 gap-6 md:grid-cols-4">
            <Fact label="From">{invoice.invoicer_snapshot.name}</Fact>
            <div>
              <div className="text-default-400 text-xs uppercase">Bill to</div>
              <Link href={`/admin/properties/${invoice.property_id}`} className="text-default-900 hover:text-primary font-medium">
                {invoice.property_snapshot.name}
              </Link>
              <div className="text-default-400 text-sm">
                {invoice.property_snapshot.city} {invoice.property_snapshot.state} {invoice.property_snapshot.zip}
              </div>
            </div>
            <Fact label="Week billed">
              {invoice.week_start && invoice.week_end ? (
                <Link href={`/admin/properties/${invoice.property_id}/grid?week=${invoice.week_start}`} className="hover:text-primary">
                  {weekLabel(invoice.week_start, invoice.week_end)}, {at(invoice.week_end).getFullYear()}
                </Link>
              ) : (
                '—'
              )}
            </Fact>
            <div className="grid grid-cols-2 gap-6">
              <Fact label="Issued">{longDate(invoice.issue_date)}</Fact>
              <Fact label="Due">{longDate(invoice.due_date)}</Fact>
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
                  {withHoliday && <th className="text-end">Holiday Hrs | Rate</th>}
                  {withTraining && <th className="text-end">Training Hrs | Rate</th>}
                  <th className="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
                {invoice.items.map((it, idx) => (
                  <tr key={idx}>
                    <td>
                      <span className="font-medium">{it.contractor_name}</span>
                      <span className="text-default-400 block text-xs">{it.position_name}</span>
                    </td>
                    <td className="text-default-500">{it.job_code ?? '—'}</td>
                    <td className="text-end whitespace-nowrap tabular-nums">
                      {hrs(it.regular_minutes)} <span className="text-default-400">| {money(it.bill_rate)}</span>
                    </td>
                    <td className="text-end whitespace-nowrap tabular-nums">
                      <HoursAndRate minutes={it.overtime_minutes} rate={it.ot_bill_rate} />
                    </td>
                    {withHoliday && (
                      <td className="text-end whitespace-nowrap tabular-nums">
                        <HoursAndRate minutes={it.holiday_minutes} rate={it.holiday_rate} />
                      </td>
                    )}
                    {withTraining && (
                      <td className="text-end whitespace-nowrap tabular-nums">
                        <HoursAndRate minutes={it.training_minutes} rate={it.training_rate} />
                      </td>
                    )}
                    <td className="text-default-900 text-end font-medium tabular-nums">{money(it.total_bill)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {invoice.position_summary.length > 0 && (
            <div className="mt-6">
              <h5 className="mb-2 font-semibold">Position summary</h5>
              <div className="table-wrapper">
                <table className="table">
                  <thead className="thead-sm">
                    <tr className="bg-light/25 text-xs uppercase">
                      <th>Position</th>
                      <th>Job Code</th>
                      <th className="text-end">Rate</th>
                      <th className="text-end">Reg Hrs</th>
                      <th className="text-end">OT Hrs</th>
                      <th className="text-end">Holiday Hrs</th>
                      {withTraining && <th className="text-end">Training Hrs</th>}
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
                        <td className="text-end tabular-nums">{hrs(row.regular_minutes)}</td>
                        <td className="text-end tabular-nums">{hrs(row.overtime_minutes)}</td>
                        <td className="text-end tabular-nums">{hrs(row.holiday_minutes)}</td>
                        {withTraining && <td className="text-end tabular-nums">{hrs(row.training_minutes)}</td>}
                        <td className="text-end font-medium tabular-nums">{hrs(row.total_minutes)}</td>
                        <td className="text-end tabular-nums">{money(row.total_bill)}</td>
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
                <tr className="border-default-300 border-t font-bold"><td className="py-1">Total</td><td className="py-1 text-end">{money(invoice.total)}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {sending && (
        <SendModal
          invoiceId={invoice.id}
          defaultEmail={invoice.notification_recipient ?? invoice.property_snapshot.billing_email ?? ''}
          resend={sent}
          onClose={() => setSending(false)}
        />
      )}
    </>
  )
}

const SendModal = ({ invoiceId, defaultEmail, resend, onClose }: { invoiceId: number; defaultEmail: string; resend: boolean; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({ recipient: defaultEmail })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/invoices/${invoiceId}/send`, { onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">{resend ? 'Resend invoice' : 'Send invoice to property'}</h4></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label htmlFor="recipient" className="form-label">Recipient email</label>
              <input id="recipient" type="email" className="form-input" value={data.recipient} onChange={(e) => setData('recipient', e.target.value)} required />
              {errors.recipient && <p className="text-danger mt-1 text-sm">{errors.recipient}</p>}
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Send</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
