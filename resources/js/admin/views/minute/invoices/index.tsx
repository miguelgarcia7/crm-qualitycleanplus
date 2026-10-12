import PageBreadcrumb from '@/components/PageBreadcrumb'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import StatusFilter from '@/components/table/StatusFilter'
import { hrs, money, monthDay, Payment, paymentStatus, Pill, weekRange } from '@/components/timesheet/PmTimesheet'
import { Stat } from '@/components/timesheet/WeekSummary'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, router } from '@inertiajs/react'

type Row = Payment & {
  id: number
  invoice_number: string
  property: string | null
  week_start: string | null
  week_end: string | null
  timesheet_id: number | null
  issue_date: string
  sent_at: string | null
  total_minutes: number
  total: number
}

type Props = {
  invoices: Row[]
  pagination: PaginationMeta
  counts: Record<'all' | 'open' | 'overdue' | 'paid', number>
  summary: {
    open_total: number
    open_count: number
    next_due: string | null
    overdue_total: number
    overdue_count: number
    paid_total: number
    paid_count: number
  }
  filters: { status: string; property: number | null }
  properties: { id: number; name: string }[]
}

const invoices = (n: number) => `${n} ${n === 1 ? 'invoice' : 'invoices'}`

const Page = ({ invoices: rows, pagination, counts, summary, filters, properties }: Props) => {
  const manyProperties = properties.length > 1
  const go = (params: Record<string, string | number | null>) =>
    router.get('/invoices', { status: filters.status, property: filters.property, ...params }, { preserveScroll: true, preserveState: true })

  return (
    <>
      <Head title="Invoices" />
      <PageBreadcrumb title="Invoices" />
      <p className="text-default-500 -mt-3 mb-6">
        {manyProperties ? 'Your properties' : (properties[0]?.name ?? 'Your property')} · one invoice for each week you approve
      </p>

      <div className="card mb-6 grid grid-cols-1 lg:grid-cols-3">
        <Stat
          label="Open balance"
          value={money(summary.open_total)}
          note={summary.open_count ? `${invoices(summary.open_count)}${summary.next_due ? ` · next due ${monthDay(summary.next_due)}` : ''}` : 'Nothing to pay'}
        />
        <Stat
          label="Overdue"
          value={summary.overdue_count ? money(summary.overdue_total) : 'None'}
          valueClass={summary.overdue_count ? 'text-danger' : 'text-success'}
          note={summary.overdue_count ? `${invoices(summary.overdue_count)} past the due date` : 'Nothing past its due date'}
        />
        <Stat label="Paid, last 30 days" value={money(summary.paid_total)} note={summary.paid_count ? invoices(summary.paid_count) : 'No payments yet'} />
      </div>

      <div className="card">
        <div className="card-header flex flex-wrap items-center justify-between gap-3 md:flex-nowrap">
          <h4 className="card-title">All invoices</h4>
          {/* The pills scroll sideways on a phone rather than widening the page. */}
          <div className="flex max-w-full flex-wrap items-center gap-2 overflow-x-auto md:flex-nowrap">
            {manyProperties && (
              <select
                className="form-select !w-auto"
                aria-label="Property"
                value={filters.property ?? ''}
                onChange={(e) => go({ property: e.target.value || null, page: null })}>
                <option value="">All properties</option>
                {properties.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            )}
            <StatusFilter
              label="Payment"
              value={filters.status}
              onChange={(status) => go({ status, page: null })}
              options={[
                { key: 'all', label: 'All', count: counts.all },
                { key: 'open', label: 'Open', count: counts.open },
                { key: 'overdue', label: 'Overdue', count: counts.overdue },
                { key: 'paid', label: 'Paid', count: counts.paid },
              ]}
            />
          </div>
        </div>

        <div className="relative overflow-x-auto">
          <table className="w-full min-w-[900px] text-sm">
            <thead>
              <tr className="bg-light/25 text-default-500 text-2xs uppercase">
                <th className="py-2.5 ps-6 pe-3 text-start font-medium">Invoice</th>
                {manyProperties && <th className="px-3 py-2.5 text-start font-medium">Property</th>}
                <th className="px-3 py-2.5 text-start font-medium">Week billed</th>
                <th className="px-3 py-2.5 text-start font-medium">Issued</th>
                <th className="px-3 py-2.5 text-start font-medium">Status</th>
                <th className="px-3 py-2.5 text-end font-medium">Hours</th>
                <th className="px-3 py-2.5 text-end font-medium">Total</th>
                <th className="py-2.5 ps-3 pe-6">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.length ? (
                rows.map((r) => {
                  const status = paymentStatus(r)
                  return (
                    <tr key={r.id} className="border-default-300 border-t align-top">
                      <td className="py-3.5 ps-6 pe-3">
                        <Link href={`/invoices/${r.id}`} className="text-default-900 hover:text-primary font-semibold whitespace-nowrap">
                          {r.invoice_number}
                        </Link>
                      </td>
                      {manyProperties && <td className="text-default-900 px-3 py-3.5">{r.property}</td>}
                      <td className="px-3 py-3.5 whitespace-nowrap">
                        {r.week_start && r.week_end ? (
                          r.timesheet_id ? (
                            <Link href={`/timesheets/${r.timesheet_id}`} className="text-primary font-medium hover:underline">
                              {weekRange(r.week_start, r.week_end)}
                            </Link>
                          ) : (
                            <span className="text-default-900">{weekRange(r.week_start, r.week_end)}</span>
                          )
                        ) : (
                          <span className="text-default-500">—</span>
                        )}
                        {r.week_end && <div className="text-default-500 text-xs">{r.week_end.slice(0, 4)}</div>}
                      </td>
                      <td className="px-3 py-3.5 whitespace-nowrap">
                        <div className="text-default-900">{monthDay(r.issue_date)}</div>
                        <div className="text-default-500 text-xs">{r.sent_at ? `Emailed ${monthDay(r.sent_at)}` : 'Not emailed yet'}</div>
                      </td>
                      <td className="px-3 py-3.5">
                        <Pill tone={status.tone}>{status.label}</Pill>
                        <div className="text-default-500 mt-1 text-xs">{status.note}</div>
                      </td>
                      <td className="px-3 py-3.5 text-end tabular-nums">{hrs(r.total_minutes)}</td>
                      <td className="text-default-900 px-3 py-3.5 text-end font-semibold tabular-nums">{money(r.total)}</td>
                      <td className="py-3.5 ps-3 pe-6 text-end whitespace-nowrap">
                        <a href={`/invoices/${r.id}/pdf`} className="btn btn-sm hover:bg-light text-primary px-2.5 font-semibold" aria-label={`Download ${r.invoice_number} as PDF`}>
                          <Icon icon="download" className="size-4" /> PDF
                        </a>
                        <Link href={`/invoices/${r.id}`} className="btn btn-sm btn-light ms-1 px-3 font-semibold">
                          View
                        </Link>
                      </td>
                    </tr>
                  )
                })
              ) : (
                <tr>
                  <td colSpan={manyProperties ? 8 : 7} className="text-default-500 border-default-300 border-t py-6 text-center">
                    {filters.status === 'all' ? 'No invoices yet. One is created when you approve a week.' : 'No invoices match this filter.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {pagination.total > 0 && (
          <div className="card-footer border-default-300 border-t px-6 py-3.5">
            <ServerPagination meta={pagination} itemsName={pagination.total === 1 ? 'invoice' : 'invoices'} onPageChange={(page) => go({ page })} />
          </div>
        )}
      </div>
    </>
  )
}

export default Page
