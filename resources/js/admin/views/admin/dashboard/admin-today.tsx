import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader, { DashboardTab } from '@/components/dashboard/DashboardHeader'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'

type Action = { label: string; href: string; icon: string; primary: boolean }
type Target = { label: string; href: string }

type Stage = { key: string; label: string; value: string; note: string; href: string; stuck: boolean }

type ChaseRow = {
  id: number
  property: string | null
  week: string | null
  manager: string | null
  managerEmail: string | null
  recruiter: string | null
  billable: number
  waitingDays: number
  overdue: boolean
  href: string
}

type Decision = { key: string; type: string; title: string; detail: string; age: string | null; action: Target }
type Expiring = { id: number; title: string; detail: string; days: number; tone: 'danger' | 'warning' | 'gray'; href: string }
type Event = { date: string; day: string; title: string; detail: string; link: Target }
type Gap = { id: number; name: string; count: number; top: string | null; href: string }

export type AdminHeader = { greeting: string; date: string; week: string; properties: number }

type Today = {
  actions: Action[]
  alerts: { key: string; title: string; body: string; actionLabel: string; actionHref: string }[]
  pipeline: { stages: Stage[]; chase: ChaseRow[]; chaseTotal: number } | null
  decisions: { rows: Decision[]; total: number; hidden: number; byType: { label: string; count: number; href: string }[] }
  expiring: { rows: Expiring[]; total: number } | null
  comingUp: { rows: Event[]; total: number }
  setupGaps: { rows: Gap[]; total: number }
}

const money = (dollars: number) => `$${dollars.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const plural = (n: number, one: string, many: string) => `${n.toLocaleString()} ${n === 1 ? one : many}`

const toneBadge: Record<Expiring['tone'], string> = {
  danger: 'bg-danger/15 text-danger',
  warning: 'bg-warning/15 text-warning',
  gray: 'bg-light text-default-600',
}

/** Hours on the way to invoices: each step, the stuck one highlighted, and who to chase. */
const Pipeline = ({ pipeline }: { pipeline: NonNullable<Today['pipeline']> }) => (
  <DashCard
    title="Hours on the way to invoices"
    description="Each step waits on someone. A step that is running late is highlighted."
    flush
    footer={
      pipeline.chaseTotal > pipeline.chase.length ? (
        <ViewAll href="/admin/timesheets?tab=pending&sort=submitted_at&direction=asc">View all {pipeline.chaseTotal} waiting</ViewAll>
      ) : undefined
    }>
    <div className="border-default-200 grid border-b sm:grid-cols-2 lg:grid-cols-5">
      {pipeline.stages.map((stage) => (
        <Link
          key={stage.key}
          href={stage.href}
          className={cn(
            'border-default-200 hover:bg-default-50 block border-b px-6 py-4 transition-colors lg:border-e lg:border-b-0 lg:last:border-e-0',
            stage.stuck && 'bg-warning/10 hover:bg-warning/15 shadow-[inset_0_-3px_0_var(--color-warning)]',
          )}>
          <span className="text-default-500 block text-sm font-medium">{stage.label}</span>
          <span className={cn('mt-1 block text-2xl font-semibold tabular-nums', stage.stuck ? 'text-warning' : 'text-default-900')}>
            {stage.value}
          </span>
          <span className={cn('mt-0.5 block text-sm', stage.stuck ? 'text-warning' : 'text-default-400')}>{stage.note}</span>
        </Link>
      ))}
    </div>

    <div className="px-6 pt-4 pb-1">
      <h5 className="text-default-900 font-semibold">Who to chase · oldest first</h5>
      <p className="text-default-400 text-sm">Timesheets waiting on the property manager's approval.</p>
    </div>

    {pipeline.chase.length === 0 ? (
      <div className="px-6 pb-2">
        <DashEmpty>Nothing is waiting on a property manager.</DashEmpty>
      </div>
    ) : (
      <div className="relative overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th className="ps-6">Property</th>
              <th>Week</th>
              <th>Property manager</th>
              <th>Sent by</th>
              <th className="text-end">Billable</th>
              <th>Waiting</th>
              <th className="pe-6">
                <span className="sr-only">Action</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {pipeline.chase.map((row) => (
              <tr key={row.id}>
                <td className="text-default-900 ps-6 font-medium">{row.property}</td>
                <td className="whitespace-nowrap">{row.week}</td>
                <td>
                  {row.manager === null ? (
                    <span className="text-default-400">No manager assigned</span>
                  ) : row.managerEmail ? (
                    <a href={`mailto:${row.managerEmail}`} className="text-primary" title={`Email ${row.manager}`}>
                      {row.manager}
                    </a>
                  ) : (
                    row.manager
                  )}
                </td>
                <td>{row.recruiter ?? '—'}</td>
                <td className="text-end whitespace-nowrap tabular-nums">{money(row.billable)}</td>
                <td>
                  <span
                    className={cn(
                      'badge badge-label whitespace-nowrap',
                      row.overdue ? 'bg-warning/15 text-warning' : 'bg-light text-default-600',
                    )}>
                    {row.waitingDays < 1 ? 'Today' : plural(row.waitingDays, 'day', 'days')}
                  </span>
                </td>
                <td className="pe-6 text-end">
                  <Link href={row.href} className="btn btn-sm border-default-300 hover:border-default-400 border whitespace-nowrap">
                    Open week
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )}
  </DashCard>
)

const Decisions = ({ decisions, className }: { decisions: Today['decisions']; className?: string }) => {
  const more = decisions.hidden > 0

  return (
    <DashCard
      className={className}
      title="Needs a decision"
      description="Requests waiting on you or your team, oldest first."
      aside={decisions.total > 0 && <span className="badge badge-label bg-light text-default-600 shrink-0">{decisions.total} waiting</span>}
      footer={
        more ? (
          <div className="flex flex-wrap gap-x-5 gap-y-1">
            {decisions.byType.map((type) => (
              <Link key={type.label} href={type.href} className="text-primary font-medium">
                {type.label} ({type.count}) →
              </Link>
            ))}
          </div>
        ) : undefined
      }>
      {decisions.rows.length === 0 ? (
        <DashEmpty>Nothing is waiting on a decision.</DashEmpty>
      ) : (
        decisions.rows.map((row) => (
          <DashRow
            key={row.key}
            end={
              <>
                {row.age && <span className="text-default-400 hidden text-sm whitespace-nowrap sm:inline">{row.age}</span>}
                <Link href={row.action.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
                  {row.action.label}
                </Link>
              </>
            }>
            <div className="flex items-start gap-3">
              <span className="badge badge-label bg-info/15 text-info mt-0.5 hidden w-28 shrink-0 justify-center sm:inline-flex">{row.type}</span>
              <div className="min-w-0">
                <div className="text-default-900 font-medium">{row.title}</div>
                <div className="text-default-400 text-sm">
                  <span className="sm:hidden">{row.type} · </span>
                  {row.detail}
                </div>
              </div>
            </div>
          </DashRow>
        ))
      )}
    </DashCard>
  )
}

const ExpiringCard = ({ expiring }: { expiring: NonNullable<Today['expiring']> }) => (
  <DashCard
    title="Expiring in 30 days"
    description="Contracts to renew before they lapse."
    footer={expiring.total > expiring.rows.length ? <ViewAll href="/admin/properties">View all {expiring.total}</ViewAll> : undefined}>
    {expiring.rows.length === 0 ? (
      <DashEmpty>Nothing expires in the next 30 days.</DashEmpty>
    ) : (
      expiring.rows.map((row) => (
        <DashRow key={row.id} end={<span className={cn('badge badge-label whitespace-nowrap', toneBadge[row.tone])}>{row.days === 0 ? 'Today' : plural(row.days, 'day', 'days')}</span>}>
          <Link href={row.href} className="text-default-900 hover:text-primary font-medium">
            {row.title}
          </Link>
          <div className="text-default-400 text-sm">{row.detail}</div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const ComingUp = ({ comingUp, className }: { comingUp: Today['comingUp']; className?: string }) => (
  <DashCard
    className={className}
    title="Coming up · next 14 days"
    description="Placements starting or ending, and staff away."
    footer={comingUp.total > comingUp.rows.length ? <span className="text-default-400">{comingUp.total - comingUp.rows.length} more later in the two weeks</span> : undefined}>
    {comingUp.rows.length === 0 ? (
      <DashEmpty>Nothing scheduled in the next two weeks.</DashEmpty>
    ) : (
      comingUp.rows.map((event, i) => (
        <DashRow
          key={`${event.date}-${i}`}
          end={
            <Link href={event.link.href} className="text-primary text-sm font-medium">
              {event.link.label}
            </Link>
          }>
          <div className="flex items-baseline gap-4">
            <span className="text-default-400 w-14 shrink-0 text-sm tabular-nums">{event.day}</span>
            <span className="min-w-0">
              <span className="text-default-900 font-medium">{event.title}</span>
              {event.detail && <span className="text-default-400"> · {event.detail}</span>}
            </span>
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const SetupGaps = ({ gaps }: { gaps: Today['setupGaps'] }) => (
  <DashCard
    title="Property setup gaps"
    description="Missing rates, contracts, contacts or clock-in setup. Most gaps first."
    footer={gaps.total > gaps.rows.length ? <ViewAll href="/admin/properties">View all {gaps.total} properties</ViewAll> : undefined}>
    {gaps.rows.length === 0 ? (
      <DashEmpty>Every property is fully set up.</DashEmpty>
    ) : (
      gaps.rows.map((gap) => (
        <DashRow key={gap.id} end={<span className="text-default-500 text-sm whitespace-nowrap">{plural(gap.count, 'gap', 'gaps')}</span>}>
          <Link href={gap.href} className="text-default-900 hover:text-primary font-medium">
            {gap.name}
          </Link>
          {gap.top && <div className="text-default-400 truncate text-sm">{gap.top}</div>}
        </DashRow>
      ))
    )}
  </DashCard>
)

const Page = ({ today, header, tabs, view }: { today: Today; header: AdminHeader; tabs: DashboardTab[]; view: string }) => {
  return (
    <>
      <DashboardHeader
        greeting={header.greeting}
        subtitle={`${header.date} · ${header.week} · All ${header.properties} properties`}
        tabs={tabs}
        current={view}
        aside={
          today.actions.length > 0 && (
            <div className="flex flex-wrap gap-2">
              {today.actions.map((action) => (
                <Link
                  key={action.href}
                  href={action.href}
                  className={cn(
                    'btn',
                    action.primary ? 'bg-primary hover:bg-primary-hover text-white' : 'border-default-300 hover:border-default-400 border',
                  )}>
                  <Icon icon={action.icon} className="size-4.5" />
                  {action.label}
                </Link>
              ))}
            </div>
          )
        }
      />

      <div className="flex flex-col gap-6">
        {today.alerts.map((alert) => (
          <div key={alert.key} role="alert" className="border-danger/30 bg-danger/5 flex flex-wrap items-start gap-4 rounded-xl border px-5 py-4">
            <Icon icon="alert-triangle" className="text-danger mt-0.5 size-5 shrink-0" />
            <div className="min-w-60 flex-1">
              <div className="text-danger font-semibold">{alert.title}</div>
              <div className="text-default-600 text-sm">{alert.body}</div>
            </div>
            <Link href={alert.actionHref} className="btn btn-sm bg-card border-default-300 border">
              {alert.actionLabel}
            </Link>
          </div>
        ))}

        {today.pipeline && <Pipeline pipeline={today.pipeline} />}

        <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
          <Decisions decisions={today.decisions} className={today.expiring ? 'xl:col-span-2' : 'xl:col-span-3'} />
          {today.expiring && <ExpiringCard expiring={today.expiring} />}
        </div>

        <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
          <ComingUp comingUp={today.comingUp} className="xl:col-span-2" />
          <SetupGaps gaps={today.setupGaps} />
        </div>
      </div>
    </>
  )
}

export default Page
