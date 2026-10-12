import PageBreadcrumb from '@/components/PageBreadcrumb'
import { hrs, money, monthDay, Payment, paymentStatus, Pill, weekRange } from '@/components/timesheet/PmTimesheet'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'
import { ReactNode } from 'react'

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

type Invoice = Payment & {
  id: number
  invoice_number: string
  issue_date: string
  sent_at: string | null
  sent_to: string | null
  week_start: string | null
  week_end: string | null
  timesheet_id: number | null
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

type PreviewNotice = { heading: string; body: string } | null

type Props = { invoice: Invoice; previewNotice: PreviewNotice }

const longDate = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const itemMinutes = (it: Item) => it.regular_minutes + it.overtime_minutes + it.holiday_minutes + it.training_minutes

/**
 * These documents are a working record — the billable invoice is issued
 * elsewhere. Saying so on the artefact itself is the only place a client
 * reads it, so it sits beside the totals rather than in a help page.
 */
const PreviewOnlyNotice = ({ notice }: { notice: PreviewNotice }) =>
  notice === null ? null : (
    <div className="bg-danger/8 flex max-w-md flex-[1_1_320px] items-start gap-3 rounded-lg p-4" role="note">
      <Icon icon="alert-circle" className="text-danger mt-0.5 size-5 shrink-0" />
      <span className="text-default-900 text-sm">
        <span className="block font-semibold">{notice.heading}</span>
        <span className="block">{notice.body}</span>
      </span>
    </div>
  )

/** Hours, with the rate they were billed at underneath. */
const HoursAt = ({ minutes, rate }: { minutes: number; rate: number | null }) =>
  minutes > 0 ? (
    <>
      {hrs(minutes)}
      {rate !== null && <span className="text-default-500 block text-xs">× {money(rate)}</span>}
    </>
  ) : (
    <span className="text-default-400">—</span>
  )

const Fact = ({ label, children }: { label: string; children: ReactNode }) => (
  <div className="min-w-0">
    <div className="text-default-500 text-[11px] font-medium tracking-wider uppercase">{label}</div>
    <div className="text-default-900 font-semibold">{children}</div>
  </div>
)

const Page = ({ invoice, previewNotice }: Props) => {
  const status = paymentStatus(invoice)
  const property = invoice.property_snapshot
  const withHoliday = invoice.items.some((it) => it.holiday_minutes > 0)
  const withTraining = invoice.items.some((it) => it.training_minutes > 0)
  const withJobCodes = invoice.items.some((it) => it.job_code)
  const totals = invoice.items.reduce(
    (t, it) => ({
      regular: t.regular + it.regular_minutes,
      overtime: t.overtime + it.overtime_minutes,
      holiday: t.holiday + it.holiday_minutes,
      training: t.training + it.training_minutes,
      hours: t.hours + itemMinutes(it),
    }),
    { regular: 0, overtime: 0, holiday: 0, training: 0, hours: 0 },
  )
  const cityLine = [[property.city, property.state].filter(Boolean).join(', '), property.zip].filter(Boolean).join(' ')
  const address = [property.address, cityLine].filter(Boolean).join(', ')

  return (
    <>
      <Head title={invoice.invoice_number} />
      <PageBreadcrumb title={invoice.invoice_number} subtitle="Invoices" />
      <p className="text-default-500 -mt-3 mb-6">
        {property.name}
        {invoice.week_start && invoice.week_end && ` · week of ${weekRange(invoice.week_start, invoice.week_end)}, ${invoice.week_end.slice(0, 4)}`}
      </p>

      <div className="card mb-4">
        <div className="card-body flex flex-wrap items-center justify-between gap-5 p-5 lg:px-6">
          <div className="flex flex-wrap items-center gap-x-8 gap-y-3">
            <Pill tone={status.tone}>{status.label}</Pill>
            <Fact label="Total">
              <span className="text-lg tabular-nums">{money(invoice.total)}</span>
            </Fact>
            <Fact label="Issued">{longDate(invoice.issue_date)}</Fact>
            <Fact label="Emailed">
              {invoice.sent_at ? (
                <>
                  {monthDay(invoice.sent_at)}
                  {invoice.sent_to && <span className="text-default-500 font-normal"> · {invoice.sent_to}</span>}
                </>
              ) : (
                <span className="text-default-500 font-normal">Not yet</span>
              )}
            </Fact>
            <Fact label={invoice.paid_at ? 'Was due' : 'Due'}>{longDate(invoice.due_date)}</Fact>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Link href="/invoices" className="btn btn-light px-4">
              All invoices
            </Link>
            {invoice.timesheet_id && (
              <Link href={`/timesheets/${invoice.timesheet_id}`} className="btn btn-light px-4">
                View the week
              </Link>
            )}
            <a href={`/invoices/${invoice.id}/pdf`} className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white">
              <Icon icon="download" className="size-4" /> Download PDF
            </a>
          </div>
        </div>
      </div>

      {invoice.voided && (
        <p className="bg-light text-default-900 mb-4 rounded-lg px-4 py-3">This invoice was voided and is no longer payable. A replacement may have been issued.</p>
      )}
      {invoice.is_overdue && (
        <p className="bg-danger/10 text-default-900 mb-4 rounded-lg px-4 py-3">
          <strong>This invoice was due {longDate(invoice.due_date)}.</strong> If it has already been paid, it can take a few days to show here.
        </p>
      )}

      <section className="card" aria-label="Invoice">
        <div className="card-body space-y-6 p-6 lg:px-8">
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
              <div className="text-default-500 text-[11px] font-medium tracking-wider uppercase">From</div>
              <div className="text-default-900 mt-1 font-semibold">{invoice.invoicer_snapshot.name}</div>
            </div>
            <div>
              <div className="text-default-500 text-[11px] font-medium tracking-wider uppercase">Bill to</div>
              <div className="text-default-900 mt-1 font-semibold">{property.name}</div>
              {address && <div className="text-sm">{address}</div>}
            </div>
          </div>

          <div className="relative -mx-3 overflow-x-auto">
            <table className="w-full min-w-[760px] text-sm">
              <thead>
                <tr className="text-default-500 text-2xs uppercase">
                  <th className="px-3 py-2.5 text-start font-medium">Contractor</th>
                  <th className="px-3 py-2.5 text-end font-medium">Regular</th>
                  <th className="px-3 py-2.5 text-end font-medium">Overtime</th>
                  {withHoliday && <th className="px-3 py-2.5 text-end font-medium">Holiday</th>}
                  {withTraining && <th className="px-3 py-2.5 text-end font-medium">Training</th>}
                  <th className="px-3 py-2.5 text-end font-medium">Hours</th>
                  <th className="px-3 py-2.5 text-end font-medium">Amount</th>
                </tr>
              </thead>
              <tbody>
                {invoice.items.map((it, idx) => (
                  <tr key={idx} className="border-default-300 border-t align-top tabular-nums">
                    <td className="px-3 py-3">
                      <span className="text-default-900 block font-semibold">{it.contractor_name}</span>
                      <span className="text-default-500 block text-xs">
                        {it.position_name}
                        {withJobCodes && it.job_code && ` · ${it.job_code}`}
                      </span>
                    </td>
                    <td className="px-3 py-3 text-end">
                      <HoursAt minutes={it.regular_minutes} rate={it.bill_rate} />
                    </td>
                    <td className="px-3 py-3 text-end">
                      <HoursAt minutes={it.overtime_minutes} rate={it.ot_bill_rate} />
                    </td>
                    {withHoliday && (
                      <td className="px-3 py-3 text-end">
                        <HoursAt minutes={it.holiday_minutes} rate={it.holiday_rate} />
                      </td>
                    )}
                    {withTraining && (
                      <td className="px-3 py-3 text-end">
                        <HoursAt minutes={it.training_minutes} rate={it.training_rate} />
                      </td>
                    )}
                    <td className="text-default-900 px-3 py-3 text-end font-semibold">{hrs(itemMinutes(it))}</td>
                    <td className="text-default-900 px-3 py-3 text-end font-semibold">{money(it.total_bill)}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-default-300 bg-light/40 text-default-900 border-t font-semibold tabular-nums">
                  <td className="text-default-500 px-3 py-3 text-xs font-medium uppercase">
                    {invoice.items.length} {invoice.items.length === 1 ? 'line' : 'lines'}
                  </td>
                  <td className="px-3 py-3 text-end">{hrs(totals.regular)}</td>
                  <td className="px-3 py-3 text-end">{hrs(totals.overtime)}</td>
                  {withHoliday && <td className="px-3 py-3 text-end">{hrs(totals.holiday)}</td>}
                  {withTraining && <td className="px-3 py-3 text-end">{hrs(totals.training)}</td>}
                  <td className="px-3 py-3 text-end">{hrs(totals.hours)}</td>
                  <td className="px-3 py-3 text-end">{money(invoice.work_subtotal)}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          {invoice.position_summary.length > 0 && (
            <div>
              <h2 className="text-default-900 mb-2 text-base font-semibold">Position summary</h2>
              <div className="relative -mx-3 overflow-x-auto">
                <table className="w-full min-w-[760px] text-sm">
                  <thead>
                    <tr className="text-default-500 text-2xs uppercase">
                      <th className="px-3 py-2.5 text-start font-medium">Position</th>
                      <th className="px-3 py-2.5 text-start font-medium">Job code</th>
                      <th className="px-3 py-2.5 text-end font-medium">Rate</th>
                      <th className="px-3 py-2.5 text-end font-medium">Regular</th>
                      <th className="px-3 py-2.5 text-end font-medium">Overtime</th>
                      <th className="px-3 py-2.5 text-end font-medium">Holiday</th>
                      {withTraining && <th className="px-3 py-2.5 text-end font-medium">Training</th>}
                      <th className="px-3 py-2.5 text-end font-medium">Hours</th>
                      <th className="px-3 py-2.5 text-end font-medium">Billed</th>
                    </tr>
                  </thead>
                  <tbody>
                    {invoice.position_summary.map((row) => (
                      <tr key={row.position} className="border-default-300 border-t tabular-nums">
                        <td className="text-default-900 px-3 py-3 font-semibold">{row.position}</td>
                        <td className="px-3 py-3">{row.job_code ?? <span className="text-default-400">—</span>}</td>
                        <td className="px-3 py-3 text-end">
                          {row.bill_rate === null ? <Pill tone="warning">Mixed</Pill> : money(row.bill_rate)}
                        </td>
                        <td className="px-3 py-3 text-end">{hrs(row.regular_minutes)}</td>
                        <td className="px-3 py-3 text-end">{hrs(row.overtime_minutes)}</td>
                        <td className="px-3 py-3 text-end">{hrs(row.holiday_minutes)}</td>
                        {withTraining && <td className="px-3 py-3 text-end">{hrs(row.training_minutes)}</td>}
                        <td className="text-default-900 px-3 py-3 text-end font-semibold">{hrs(row.total_minutes)}</td>
                        <td className="text-default-900 px-3 py-3 text-end font-semibold">{money(row.total_bill)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <div className="flex flex-wrap items-start justify-between gap-5">
            <PreviewOnlyNotice notice={previewNotice} />
            <table className="ms-auto w-72 text-sm tabular-nums">
              <tbody>
                <tr>
                  <td className="py-1">Work</td>
                  <td className="py-1 text-end">{money(invoice.work_subtotal)}</td>
                </tr>
                <tr>
                  <td className="py-1">Adjustments</td>
                  {/* Signed: a credit reads as a credit rather than as a smaller bill. */}
                  <td className={cn('py-1 text-end', invoice.adjustment_total < 0 && 'text-danger')}>
                    {invoice.adjustment_total < 0 ? '−' : ''}
                    {money(Math.abs(invoice.adjustment_total))}
                  </td>
                </tr>
                <tr>
                  <td className="py-1">Subtotal</td>
                  <td className="py-1 text-end">{money(invoice.subtotal)}</td>
                </tr>
                <tr>
                  {/* The rate beside the amount: a bare $0.00 cannot say whether the
                      rate is zero or the calculation went wrong. */}
                  <td className="py-1">Sales tax ({(invoice.tax_rate * 100).toFixed(2).replace(/\.?0+$/, '')}%)</td>
                  <td className="py-1 text-end">{money(invoice.tax_amount)}</td>
                </tr>
                <tr className="border-default-300 text-default-900 border-t text-base font-bold">
                  <td className="pt-2.5">Total</td>
                  <td className="pt-2.5 text-end">{money(invoice.total)}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </>
  )
}

export default Page
