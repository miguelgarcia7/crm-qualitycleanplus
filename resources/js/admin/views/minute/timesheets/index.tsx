import PageBreadcrumb from '@/components/PageBreadcrumb'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import StatusFilter from '@/components/table/StatusFilter'
import { byWhom, Chip, hrs, money, monthDay, StatusPill, weekRange } from '@/components/timesheet/PmTimesheet'
import { Head, Link, router } from '@inertiajs/react'

type Row = {
  id: number
  property: string | null
  week_start: string
  week_end: string
  status: string
  sent_at: string | null
  sent_by: string | null
  waiting_days: number | null
  approved_at: string | null
  approved_by: string | null
  declined_at: string | null
  declined_by: string | null
  decline_reason: string | null
  people: number
  total_minutes: number
  overtime_minutes: number
  holiday_minutes: number
  amount: number
  invoice_id: number | null
  invoice_number: string | null
}

type ThisWeek = { property_id: number; property: string; week_start: string; week_end: string; total_minutes: number; on_the_clock: number }

type Props = {
  waiting: Row[]
  timesheets: Row[]
  pagination: PaginationMeta
  counts: Record<'all' | 'waiting' | 'declined' | 'invoiced', number>
  filters: { status: string; property: number | null }
  properties: { id: number; name: string }[]
  this_week: ThisWeek[]
}

const waited = (days: number | null) => (days === null ? '' : days === 0 ? 'today' : days === 1 ? '1 day' : `${days} days`)

const statusNote = (r: Row) => {
  if (r.status === 'pending_approval') return r.sent_at ? `Sent ${monthDay(r.sent_at)}${r.waiting_days ? ` · ${waited(r.waiting_days)} ago` : ''}` : ''
  if (r.status === 'declined') return byWhom(r.declined_by, 'declined', r.declined_at)
  if (r.status === 'voided') return 'The invoice was voided'
  return r.approved_at ? byWhom(r.approved_by, 'approved', r.approved_at) : ''
}

const Page = ({ waiting, timesheets, pagination, counts, filters, properties, this_week }: Props) => {
  const manyProperties = properties.length > 1
  const go = (params: Record<string, string | number | null>) =>
    router.get('/timesheets', { status: filters.status, property: filters.property, ...params }, { preserveScroll: true, preserveState: true })

  return (
    <>
      <Head title="Timesheets" />
      <PageBreadcrumb title="Timesheets" />
      <p className="text-default-500 -mt-3 mb-6">
        {manyProperties ? 'Your properties' : (properties[0]?.name ?? 'Your property')} · each week your recruiter sends you, and what was invoiced
      </p>

      <div className="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-3">
        {waiting.map((r) => (
          <section key={r.id} className="card xl:col-span-2" aria-labelledby={`waiting-${r.id}`}>
            <div className="card-body flex flex-wrap items-center gap-5 p-5 lg:px-6">
              <div className="min-w-0 flex-[1_1_420px]">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <StatusPill status="pending_approval">{`Waiting for you${r.waiting_days ? ` · ${waited(r.waiting_days)}` : ''}`}</StatusPill>
                  {r.sent_at && <span className="text-default-500 text-sm">{byWhom(r.sent_by, 'sent', r.sent_at)}</span>}
                </div>
                <h2 id={`waiting-${r.id}`} className="text-default-900 mt-2.5 mb-1 text-lg font-semibold">
                  Week of {weekRange(r.week_start, r.week_end)}, {r.week_end.slice(0, 4)}
                  {manyProperties && <span className="text-default-500 font-normal"> · {r.property}</span>}
                </h2>
                <div className="flex flex-wrap gap-x-5 gap-y-1">
                  <span>
                    <strong className="text-default-900">{r.people}</strong> {r.people === 1 ? 'person' : 'people'}
                  </span>
                  <span>
                    <strong className="text-default-900">{hrs(r.total_minutes)}</strong> hours
                  </span>
                  {r.overtime_minutes > 0 && (
                    <span>
                      <strong className="text-default-900">{hrs(r.overtime_minutes)}</strong> overtime
                    </span>
                  )}
                  <span>
                    <strong className="text-default-900">{money(r.amount)}</strong> before tax
                  </span>
                </div>
              </div>
              <Link href={`/timesheets/${r.id}`} className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white">
                Review and approve
              </Link>
            </div>
          </section>
        ))}

        {this_week.map((w) => (
          <section key={w.property_id} className="card" aria-labelledby={`week-${w.property_id}`}>
            <div className="card-body flex h-full flex-col justify-between gap-4 p-5 lg:px-6">
              <div>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <StatusPill status="draft">In progress</StatusPill>
                  {w.on_the_clock > 0 && <span className="text-default-500 text-sm">{w.on_the_clock} on the clock now</span>}
                </div>
                <h2 id={`week-${w.property_id}`} className="text-default-900 mt-2.5 mb-1 text-lg font-semibold">
                  This week · {weekRange(w.week_start, w.week_end)}
                </h2>
                <p className="text-default-500 text-sm">
                  {manyProperties && `${w.property} · `}
                  {hrs(w.total_minutes)} hours so far. Your recruiter sends it once the week closes.
                </p>
              </div>
              <div>
                <Link href={`/timesheets/this-week?property=${w.property_id}`} className="btn btn-light px-4 font-semibold">
                  View this week
                </Link>
              </div>
            </div>
          </section>
        ))}
      </div>

      <div className="card">
        <div className="card-header flex flex-wrap items-center justify-between gap-3 md:flex-nowrap">
          <h4 className="card-title">All weeks</h4>
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
              label="Status"
              value={filters.status}
              onChange={(status) => go({ status, page: null })}
              options={[
                { key: 'all', label: 'All', count: counts.all },
                { key: 'waiting', label: 'Waiting for you', count: counts.waiting },
                { key: 'declined', label: 'Declined', count: counts.declined },
                { key: 'invoiced', label: 'Invoiced', count: counts.invoiced },
              ]}
            />
          </div>
        </div>

        <div className="relative overflow-x-auto">
          <table className="w-full min-w-[880px] text-sm">
            <thead>
              <tr className="bg-light/25 text-default-500 text-2xs uppercase">
                <th className="py-2.5 ps-6 pe-3 text-start font-medium">Week</th>
                {manyProperties && <th className="px-3 py-2.5 text-start font-medium">Property</th>}
                <th className="px-3 py-2.5 text-start font-medium">Status</th>
                <th className="px-3 py-2.5 text-end font-medium">People</th>
                <th className="px-3 py-2.5 text-end font-medium">Hours</th>
                <th className="px-3 py-2.5 text-end font-medium">Amount</th>
                <th className="px-3 py-2.5 text-start font-medium">Invoice</th>
                <th className="py-2.5 ps-3 pe-6">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {timesheets.length ? (
                timesheets.map((r) => (
                  <tr key={r.id} className="border-default-300 border-t align-top">
                    <td className="py-3.5 ps-6 pe-3">
                      <div className="text-default-900 font-semibold whitespace-nowrap">{weekRange(r.week_start, r.week_end)}</div>
                      <div className="text-default-500 text-xs">{r.week_end.slice(0, 4)}</div>
                    </td>
                    {manyProperties && <td className="text-default-900 px-3 py-3.5">{r.property}</td>}
                    <td className="px-3 py-3.5">
                      <StatusPill status={r.status} />
                      <div className="text-default-500 mt-1 text-xs">{statusNote(r)}</div>
                      {r.decline_reason && <div className="text-default-500 mt-0.5 max-w-64 truncate text-xs" title={r.decline_reason}>“{r.decline_reason}”</div>}
                    </td>
                    <td className="px-3 py-3.5 text-end tabular-nums">{r.people}</td>
                    <td className="px-3 py-3.5 text-end tabular-nums">
                      <div className="text-default-900 font-semibold">{hrs(r.total_minutes)}</div>
                      <div className="mt-0.5 flex flex-wrap justify-end gap-1">
                        {r.overtime_minutes > 0 && <Chip tone="secondary">{`+${hrs(r.overtime_minutes)} OT`}</Chip>}
                        {r.holiday_minutes > 0 && <Chip tone="info">{`${hrs(r.holiday_minutes)} holiday`}</Chip>}
                      </div>
                    </td>
                    <td className="px-3 py-3.5 text-end tabular-nums">
                      <div className="text-default-900 font-semibold">{money(r.amount)}</div>
                      <div className="text-default-500 text-xs">before tax</div>
                    </td>
                    <td className="px-3 py-3.5 whitespace-nowrap">
                      {r.invoice_id ? (
                        <Link href={`/invoices/${r.invoice_id}`} className="text-primary font-medium hover:underline">
                          {r.invoice_number}
                        </Link>
                      ) : (
                        <span className="text-default-500">{r.status === 'voided' ? '—' : 'After approval'}</span>
                      )}
                    </td>
                    <td className="py-3.5 ps-3 pe-6 text-end">
                      {r.status === 'pending_approval' ? (
                        <Link href={`/timesheets/${r.id}`} className="btn btn-sm bg-primary hover:bg-primary-hover px-3 font-semibold text-white">
                          Review
                        </Link>
                      ) : (
                        <Link href={`/timesheets/${r.id}`} className="btn btn-sm btn-light px-3 font-semibold">
                          View
                        </Link>
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={manyProperties ? 8 : 7} className="text-default-500 border-default-300 border-t py-6 text-center">
                    {filters.status === 'all' ? 'No weeks have been sent to you yet.' : 'No weeks match this filter.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {pagination.total > 0 && (
          <div className="card-footer border-default-300 border-t px-6 py-3.5">
            <ServerPagination meta={pagination} itemsName={pagination.total === 1 ? 'week' : 'weeks'} onPageChange={(page) => go({ page })} />
          </div>
        )}
      </div>
    </>
  )
}

export default Page
