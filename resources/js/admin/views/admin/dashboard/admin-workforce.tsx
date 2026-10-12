import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader, { DashboardTab } from '@/components/dashboard/DashboardHeader'
import DirectHireCard, { DirectHire } from '@/components/dashboard/DirectHireCard'
import StaffAwayCard, { StaffAway } from '@/components/dashboard/StaffAwayCard'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'
import type { AdminHeader } from './admin-today'

type Stat = { key: string; label: string; value: number; note: string; warn: boolean; href: string }
type PropertyRow = { id: number; name: string; contractors: number; needed: number }
type Posting = { id: number; title: string; where: string | null; applied: number }
type Recruiter = { id: number; name: string; properties: number; contractors: number; visits: number; openVisits: number; notSent: number }

type Workforce = {
  headline: Stat[]
  byProperty: { rows: PropertyRow[]; total: number }
  hiring: { applied: number; inReview: number; placed: number; postings: Posting[]; openPostings: number }
  recruiters: Recruiter[]
  directHire: DirectHire
  staffAway: StaffAway
}

const plural = (n: number, one: string, many: string) => `${n.toLocaleString()} ${n === 1 ? one : many}`

const StatCard = ({ stat }: { stat: Stat }) => (
  <Link href={stat.href} className="card hover:border-default-300 mb-0 h-full border border-transparent transition-colors">
    <div className="card-body">
      <span className="text-default-500 block text-sm font-medium">{stat.label}</span>
      <span className="text-default-900 mt-1 block text-2xl font-semibold tabular-nums">{stat.value.toLocaleString()}</span>
      <span className={cn('mt-0.5 block text-sm', stat.warn ? 'text-warning' : 'text-default-500')}>{stat.note}</span>
    </div>
  </Link>
)

const ByProperty = ({ data }: { data: Workforce['byProperty'] }) => {
  const peak = Math.max(1, ...data.rows.map((r) => r.contractors))

  return (
    <DashCard
      className="xl:col-span-2"
      title="Contractors by property"
      description="Active placements, largest first. Open requests show where you're short."
      footer={data.total > data.rows.length ? <ViewAll href="/admin/properties">All {data.total} properties</ViewAll> : undefined}>
      {data.rows.length === 0 ? (
        <DashEmpty>No contractors are placed yet.</DashEmpty>
      ) : (
        <div className="py-2">
          {data.rows.map((row) => (
            <div key={row.id} className="grid grid-cols-[minmax(0,12rem)_1fr_2.5rem] items-center gap-4 py-2">
              <span className="flex min-w-0 items-center gap-2">
                <Link href={`/admin/properties/${row.id}`} className="text-default-900 hover:text-primary truncate font-medium">
                  {row.name}
                </Link>
                {row.needed > 0 && <span className="badge badge-label bg-warning/15 text-warning shrink-0">+{row.needed} needed</span>}
              </span>
              <span className="bg-light h-2.5 overflow-hidden rounded-full" aria-hidden="true">
                <span className="bg-primary block h-full rounded-full" style={{ width: `${(row.contractors / peak) * 100}%` }} />
              </span>
              <span className="text-default-900 text-end font-semibold tabular-nums">{row.contractors}</span>
            </div>
          ))}
        </div>
      )}
    </DashCard>
  )
}

const Hiring = ({ hiring }: { hiring: Workforce['hiring'] }) => (
  <DashCard
    title="Hiring"
    description="This month, from application to placement."
    footer={hiring.openPostings > 0 ? <ViewAll href="/admin/job-postings">All {plural(hiring.openPostings, 'open posting', 'open postings')}</ViewAll> : undefined}>
    <div className="border-default-200 grid grid-cols-3 gap-3 border-b py-3">
      {[
        { label: 'applied', value: hiring.applied, href: '/admin/applicants?status=all' },
        { label: 'in review', value: hiring.inReview, href: '/admin/applicants?status=reviewing' },
        { label: 'placed', value: hiring.placed, href: '/admin/applicants?status=promoted' },
      ].map((step) => (
        <Link key={step.label} href={step.href} className="group">
          <span className="text-default-900 group-hover:text-primary block text-2xl font-semibold tabular-nums">{step.value}</span>
          <span className="text-default-500 block text-sm">{step.label}</span>
        </Link>
      ))}
    </div>
    {hiring.postings.length === 0 ? (
      <DashEmpty>No postings are open.</DashEmpty>
    ) : (
      hiring.postings.map((posting) => (
        <DashRow key={posting.id} end={<span className="text-default-500 text-sm whitespace-nowrap">{posting.applied} applied</span>}>
          <div className="text-default-900 font-medium">{posting.title}</div>
          {posting.where && <div className="text-default-500 text-sm">{posting.where}</div>}
        </DashRow>
      ))
    )}
  </DashCard>
)

const Recruiters = ({ rows }: { rows: Recruiter[] }) => (
  <DashCard className="xl:col-span-3" flush title="Recruiters this week" description="Their properties, field visits, and finished weeks with hours not yet sent for approval.">
    {rows.length === 0 ? (
      <div className="px-6 py-2">
        <DashEmpty>No recruiters yet.</DashEmpty>
      </div>
    ) : (
      <div className="relative overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th className="ps-6">Recruiter</th>
              <th className="text-end">Properties</th>
              <th className="text-end">Contractors</th>
              <th className="text-end">Field visits</th>
              <th>Visits left open</th>
              <th className="pe-6 text-end">Timesheets not sent</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td className="ps-6">
                  <Link href={`/admin/people/${r.id}`} className="text-default-900 hover:text-primary font-medium">
                    {r.name}
                  </Link>
                </td>
                <td className="text-end tabular-nums">{r.properties}</td>
                <td className="text-end tabular-nums">{r.contractors}</td>
                <td className="text-end tabular-nums">{r.visits}</td>
                <td>
                  {r.openVisits > 0 ? (
                    <Link href="/admin/field-visits?filter=open" className="badge badge-label bg-warning/15 text-warning">
                      {r.openVisits} open
                    </Link>
                  ) : (
                    <span className="text-default-500">None</span>
                  )}
                </td>
                <td className="pe-6 text-end tabular-nums">
                  {r.notSent > 0 ? (
                    <Link href="/admin/timesheets?tab=draft" className="badge badge-label bg-warning/15 text-warning">
                      {r.notSent}
                    </Link>
                  ) : (
                    0
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )}
  </DashCard>
)

const Page = ({ workforce, header, tabs, view }: { workforce: Workforce; header: AdminHeader; tabs: DashboardTab[]; view: string }) => (
  <>
    <DashboardHeader greeting={header.greeting} subtitle={`${header.date} · All ${header.properties} properties`} tabs={tabs} current={view} />

    <div className="flex flex-col gap-6">
      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        {workforce.headline.map((stat) => (
          <StatCard key={stat.key} stat={stat} />
        ))}
      </div>

      <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
        <ByProperty data={workforce.byProperty} />
        <Hiring hiring={workforce.hiring} />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <Recruiters rows={workforce.recruiters} />
      </div>

      <div className="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-2">
        <DirectHireCard
          data={workforce.directHire}
          description={`Contractors a property may soon hire directly, within ${workforce.directHire.withinWeeks} weeks at their recent pace.`}
        />
        <StaffAwayCard data={workforce.staffAway} description="Approved time off, next 30 days." />
      </div>
    </div>
  </>
)

export default Page
