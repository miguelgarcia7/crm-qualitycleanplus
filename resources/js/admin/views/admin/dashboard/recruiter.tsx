import { confirmAction } from '@/components/ConfirmHost'
import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import DirectHireCard, { DirectHire } from '@/components/dashboard/DirectHireCard'
import ExpiringCard, { Expiring } from '@/components/dashboard/ExpiringCard'
import RequestList, { Requests } from '@/components/dashboard/RequestList'
import Icon from '@/components/wrappers/Icon'
import { openCheckIn } from '@/layouts/components/CheckInFab'
import { cn } from '@/utils/helpers'
import { Link, router } from '@inertiajs/react'

type WeekAction = { label: string; href?: string; submitId?: number }

type Week = {
  id: number
  property: string | null
  week: string
  hours: number
  openPunches: number
  status: string
  statusLabel: string
  action: WeekAction | null
  href: string
}

type Manager = { id: number; property: string | null; week: string | null; manager: string | null; managerEmail: string | null; waitingDays: number; overdue: boolean; href: string }
type Visit = { id: number; name: string; lastVisit: string | null; days: number | null; warn: boolean }

type Home = {
  header: { greeting: string; date: string; properties: number; contractors: number }
  actions: { checkIn: boolean; newWorkOrder: boolean }
  lastWeek: { week: string | null; rows: Week[]; total: number; submitted: number }
  waiting: Requests
  managers: { rows: Manager[]; total: number; slaDays: number }
  expiring: Expiring
  directHire: DirectHire
  visits: { rows: Visit[]; thisWeek: number; open: number }
}

const plural = (n: number, one: string, many: string) => `${n.toLocaleString()} ${n === 1 ? one : many}`

const statusBadge: Record<string, string> = {
  draft: 'bg-warning/15 text-warning',
  declined: 'bg-danger/15 text-danger',
}

const submit = (week: Week, timesheetId: number) =>
  confirmAction({
    title: 'Send for approval',
    message: (
      <>
        Send <strong className="text-default-900">{week.property}</strong> for <strong className="text-default-900">{week.week}</strong> ({week.hours.toFixed(2)} h)
        to the property manager?
        <span className="text-default-400 mt-2 block">The week is locked while it is with them.</span>
      </>
    ),
    confirmLabel: 'Send',
    tone: 'primary',
    onConfirm: () => router.post(`/admin/timesheets/${timesheetId}/submit`, {}, { preserveScroll: true }),
  })

/** The weekly job: last week's timesheets, open punches first, then weeks to submit. */
const LastWeek = ({ data }: { data: Home['lastWeek'] }) => {
  const toDo = data.rows.length

  return (
    <DashCard
      flush
      title={data.week ? `Last week's timesheets · ${data.week}` : "Last week's timesheets"}
      description="Fix open punches, then submit each property to its manager."
      aside={
        data.total > 0 && (
          <span className={cn('badge badge-label shrink-0', toDo > 0 ? 'bg-warning/15 text-warning' : 'bg-success/15 text-success')}>
            {toDo > 0 ? `${toDo} of ${data.total} not submitted` : 'All submitted'}
          </span>
        )
      }
      footer={
        data.total > 0 ? (
          <div className="flex w-full flex-wrap items-center justify-between gap-2">
            <span className="text-default-400">{plural(data.submitted, 'property', 'properties')} already submitted</span>
            <ViewAll href="/admin/timesheets">All timesheets</ViewAll>
          </div>
        ) : undefined
      }>
      {data.total === 0 ? (
        <div className="px-6 py-2">
          <DashEmpty>No hours were logged at your properties last week.</DashEmpty>
        </div>
      ) : toDo === 0 ? (
        <div className="px-6 py-2">
          <DashEmpty>Every property is submitted. Nice work.</DashEmpty>
        </div>
      ) : (
        <div className="relative overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th className="ps-6">Property</th>
                <th className="text-end">Hours</th>
                <th>Open punches</th>
                <th>Status</th>
                <th className="pe-6">
                  <span className="sr-only">Action</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {data.rows.map((week) => (
                <tr key={week.id}>
                  <td className="ps-6">
                    <Link href={week.href} className="text-default-900 hover:text-primary font-medium">
                      {week.property}
                    </Link>
                  </td>
                  <td className="text-end tabular-nums">{week.hours.toFixed(2)}</td>
                  <td>
                    {week.openPunches > 0 ? (
                      <span className="badge badge-label bg-danger/15 text-danger">{week.openPunches} to fix</span>
                    ) : (
                      <span className="text-default-400">None</span>
                    )}
                  </td>
                  <td>
                    <span className={cn('badge badge-label', statusBadge[week.status] ?? 'bg-light text-default-600')}>{week.statusLabel}</span>
                  </td>
                  <td className="pe-6 text-end">
                    {week.action?.submitId !== undefined ? (
                      <button type="button" className="btn btn-sm bg-primary hover:bg-primary-hover text-white" onClick={() => submit(week, week.action!.submitId!)}>
                        {week.action.label}
                      </button>
                    ) : week.action?.href ? (
                      <Link href={week.action.href} className="btn btn-sm border-default-300 hover:border-default-400 border whitespace-nowrap">
                        {week.action.label}
                      </Link>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </DashCard>
  )
}

const Managers = ({ data }: { data: Home['managers'] }) => (
  <DashCard
    title="Waiting on property managers"
    description="Submitted, not yet approved. Oldest first."
    footer={
      data.total > data.rows.length ? (
        <ViewAll href="/admin/timesheets?tab=pending&sort=submitted_at&direction=asc">View all {data.total} waiting</ViewAll>
      ) : (
        <span className="text-default-400">Anything over {data.slaDays} days is flagged.</span>
      )
    }>
    {data.rows.length === 0 ? (
      <DashEmpty>Nothing is waiting on a property manager.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          end={
            <span className={cn('badge badge-label whitespace-nowrap', row.overdue ? 'bg-warning/15 text-warning' : 'bg-light text-default-600')}>
              {row.waitingDays < 1 ? 'Today' : plural(row.waitingDays, 'day', 'days')}
            </span>
          }>
          <Link href={row.href} className="text-default-900 hover:text-primary font-medium">
            {row.property}
          </Link>
          <div className="text-default-400 text-sm">
            {row.week}
            {row.manager && (
              <>
                {' · '}
                {row.managerEmail ? (
                  <a href={`mailto:${row.managerEmail}`} className="text-primary" title={`Email ${row.manager}`}>
                    {row.manager}
                  </a>
                ) : (
                  row.manager
                )}
              </>
            )}
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Visits = ({ data }: { data: Home['visits'] }) => (
  <DashCard
    title="Visits"
    description="Your properties you haven't checked in at lately."
    footer={
      <span className="text-default-400">
        {plural(data.thisWeek, 'visit', 'visits')} this week · {data.open > 0 ? <span className="text-warning">{data.open} left open</span> : 'none left open'}
      </span>
    }>
    {data.rows.length === 0 ? (
      <DashEmpty>You have no properties assigned yet.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          end={
            <span className={cn('badge badge-label whitespace-nowrap', row.warn ? 'bg-warning/15 text-warning' : 'bg-light text-default-600')}>
              {row.days === null ? 'Never' : row.days === 0 ? 'Today' : plural(row.days, 'day', 'days')}
            </span>
          }>
          <Link href={`/admin/properties/${row.id}`} className="text-default-900 hover:text-primary font-medium">
            {row.name}
          </Link>
          <div className="text-default-400 text-sm">{row.lastVisit ? `Last visit ${row.lastVisit}` : 'No visit yet'}</div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Page = ({ home }: { home: Home }) => (
  <>
    <DashboardHeader
      greeting={home.header.greeting}
      subtitle={`${home.header.date} · Your ${plural(home.header.properties, 'property', 'properties')} · ${plural(home.header.contractors, 'contractor', 'contractors')}`}
      aside={
        (home.actions.checkIn || home.actions.newWorkOrder) && (
          <div className="flex flex-wrap gap-2">
            {home.actions.checkIn && (
              <button type="button" onClick={openCheckIn} className="btn border-default-300 hover:border-default-400 border">
                <Icon icon="map-pin" className="size-4.5" />
                Check in at a property
              </button>
            )}
            {home.actions.newWorkOrder && (
              <Link href="/admin/work-orders/create" className="btn bg-primary hover:bg-primary-hover text-white">
                <Icon icon="plus" className="size-4.5" />
                New work order
              </Link>
            )}
          </div>
        )
      }
    />

    <div className="flex flex-col gap-6">
      <LastWeek data={home.lastWeek} />

      <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
        <RequestList
          className="xl:col-span-2"
          title="Waiting on you"
          description="Requests from your properties and applicants, oldest first."
          emptyText="Nothing is waiting on you."
          data={home.waiting}
        />
        <Managers data={home.managers} />
      </div>

      <div className="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <ExpiringCard data={home.expiring} description="Contracts at your properties." />
        <DirectHireCard data={home.directHire} description="Talk to the property before they hire directly." />
        <Visits data={home.visits} />
      </div>
    </div>
  </>
)

export default Page
