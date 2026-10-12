import PageBreadcrumb from '@/components/PageBreadcrumb'
import { monthDay, Pill } from '@/components/timesheet/PmTimesheet'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'

type DirectHire = {
  threshold_hours: number
  worked_hours: number
  remaining_hours: number
  percent: number
  eligible: boolean
  unrestricted: boolean
}

type Row = {
  id: number
  name: string | null
  position: string | null
  property: string | null
  started_on: string | null
  direct_hire: DirectHire | null
}

type Props = { contractors: Row[]; many_properties: boolean; can: { request_raise: boolean; request_staff: boolean } }

const hours = (n: number) => Math.round(n).toLocaleString('en-US')
const initials = (name: string | null) =>
  (name ?? '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('')

/** "3 weeks", "5 months", "2 years", from a start date to today. */
const tenure = (start: string) => {
  const days = Math.max(0, Math.floor((Date.now() - new Date(start + 'T12:00:00').getTime()) / 86_400_000))
  if (days < 14) return days === 1 ? '1 day' : `${days} days`
  if (days < 60) return `${Math.floor(days / 7)} weeks`
  if (days < 730) return `${Math.floor(days / 30.4)} months`
  return `${Math.floor(days / 365)} years`
}

const DirectHireCell = ({ dh }: { dh: DirectHire | null }) => {
  if (dh === null) return <span className="text-default-500">—</span>
  if (dh.unrestricted) return <Pill tone="success">No restriction</Pill>

  return (
    <div className="flex items-center gap-3">
      <span
        className="bg-default-200 h-2 min-w-20 flex-1 overflow-hidden rounded-full"
        role="progressbar"
        aria-label={`${hours(dh.worked_hours)} of ${hours(dh.threshold_hours)} hours toward direct hire`}
        aria-valuenow={dh.percent}
        aria-valuemin={0}
        aria-valuemax={100}>
        <span className={cn('block h-full rounded-full', dh.eligible ? 'bg-success' : 'bg-primary')} style={{ width: `${Math.max(2, dh.percent)}%` }} />
      </span>
      <span className="w-32 shrink-0 text-end text-sm tabular-nums">
        <strong className="text-default-900">{hours(dh.worked_hours)}</strong> of {hours(dh.threshold_hours)} h
        <span className="text-default-500 block text-xs">{dh.eligible ? 'Eligible now' : `${hours(dh.remaining_hours)} to go`}</span>
      </span>
    </div>
  )
}

const Page = ({ contractors, many_properties: many, can }: Props) => {
  const scope = many ? 'Your properties' : (contractors[0]?.property ?? 'Your property')

  return (
    <>
      <Head title="Contractors" />
      <PageBreadcrumb title="Contractors" />
      <div className="-mt-3 mb-6 flex flex-wrap items-end justify-between gap-3">
        <p className="text-default-500">
          {scope} · {contractors.length} placed with you
        </p>
        {can.request_staff && (
          <Link href="/staffing-requests?new=1" className="btn bg-primary hover:bg-primary-hover px-4 font-semibold text-white">
            Request staff
          </Link>
        )}
      </div>

      <div className="card">
        <div className="card-header flex flex-wrap items-center justify-between gap-3">
          <h4 className="card-title">On site</h4>
          <span className="text-default-500 text-sm">Sorted by name</span>
        </div>

        <div className="relative overflow-x-auto">
          <table className="w-full min-w-[820px] text-sm">
            <thead>
              <tr className="bg-light/25 text-default-500 text-2xs uppercase">
                <th className="py-2.5 ps-6 pe-3 text-start font-medium">Contractor</th>
                {many && <th className="px-3 py-2.5 text-start font-medium">Property</th>}
                <th className="px-3 py-2.5 text-start font-medium">With you since</th>
                <th className="w-[38%] px-3 py-2.5 text-start font-medium">Toward direct hire</th>
                <th className="py-2.5 ps-3 pe-6">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {contractors.length ? (
                contractors.map((row) => (
                  <tr key={row.id} className="border-default-300 border-t">
                    <td className="py-3 ps-6 pe-3">
                      <div className="flex items-center gap-2.5">
                        <span className="bg-primary/12 text-primary-hover grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold" aria-hidden="true">
                          {initials(row.name)}
                        </span>
                        <span className="min-w-0">
                          <span className="text-default-900 block font-semibold">{row.name}</span>
                          <span className="text-default-500 block text-xs">{row.position}</span>
                        </span>
                      </div>
                    </td>
                    {many && <td className="text-default-900 px-3 py-3">{row.property}</td>}
                    <td className="px-3 py-3 whitespace-nowrap">
                      {row.started_on ? (
                        <>
                          <span className="text-default-900">
                            {monthDay(row.started_on)}
                            {row.started_on.slice(0, 4) !== String(new Date().getFullYear()) && `, ${row.started_on.slice(0, 4)}`}
                          </span>
                          <span className="text-default-500 block text-xs">{tenure(row.started_on)}</span>
                        </>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="px-3 py-3">
                      <DirectHireCell dh={row.direct_hire} />
                    </td>
                    <td className="py-3 ps-3 pe-6 text-end">
                      {can.request_raise && (
                        <Link href={`/pay-increases?work_order=${row.id}`} className="btn btn-sm btn-light px-3 font-semibold whitespace-nowrap">
                          Request a raise
                        </Link>
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={many ? 5 : 4} className="text-default-500 border-default-300 border-t py-6 text-center">
                    No contractors are placed with you right now.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="card-footer border-default-300 border-t px-6 py-3.5">
          <p className="text-default-500 text-sm">
            Direct hire: the hours a contractor works at your property before you may offer them a position directly. Every hour counts, including
            the time before a pay raise.
          </p>
        </div>
      </div>
    </>
  )
}

export default Page
