import Icon from '@/components/wrappers/Icon'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import { cn } from '@/utils/helpers'
import { Head } from '@inertiajs/react'
import ActivityCard, { ActivityRow } from './widgets/ActivityCard'
import AlertBanner, { PulseAlert } from './widgets/AlertBanner'
import ApprovalsTable, { Approvals } from './widgets/ApprovalsTable'
import DecisionsCard, { Decisions } from './widgets/DecisionsCard'
import ListCard, { ListWidget } from './widgets/ListCard'
import LiveRosterCard, { LiveRoster } from './widgets/LiveRosterCard'
import OnTheClockCard, { OnTheClock } from './widgets/OnTheClockCard'
import PipelineStrip, { Pipeline } from './widgets/PipelineStrip'
import PulseStat, { PulseStatData } from './widgets/PulseStat'
import QuickActions, { QuickAction } from './widgets/QuickActions'
import StatCard, { Stat } from './widgets/StatCard'
import TasksCard, { Tasks } from './widgets/TasksCard'
import TrendChart, { Trend } from './widgets/TrendChart'

type Widgets = {
  // Role-specific widgets (Phase 06) that sit below the landing panels.
  stats: Stat[]
  lists: ListWidget[]
  charts: Trend[]
  actions: QuickAction[]
  activity: ActivityRow[] | null
  // Landing panels.
  context: { week_label: string; property_count: number; scoped: boolean }
  alerts: PulseAlert[]
  headline: PulseStatData[]
  pipeline: Pipeline | null
  tasks: Tasks
  approvals: Approvals | null
  onTheClockNow: LiveRoster | null
  onTheClock: OnTheClock | null
  decisions: Decisions | null
}

type Props = { widgets: Widgets }

const Page = ({ widgets }: Props) => {
  const { context } = widgets
  // The revenue-vs-payouts chart leads; anything else drops below.
  const [heroChart, ...restCharts] = widgets.charts

  // Live ops read together — who is on now, where they are, and what just
  // happened — so they share a row rather than stacking down one column.
  const liveOps = [
    widgets.onTheClockNow && <LiveRosterCard key="roster" data={widgets.onTheClockNow} />,
    widgets.onTheClock && <OnTheClockCard key="by-property" data={widgets.onTheClock} />,
    widgets.activity !== null && <ActivityCard key="activity" rows={widgets.activity} />,
  ].filter(Boolean)

  // The approval queue is a worklist, not a pulse reading — it sits with the
  // weekly chart near the bottom rather than above the live cards.
  const worklists = [
    ...restCharts.map((trend, i) => <TrendChart key={`chart-${i}`} trend={trend} />),
    widgets.approvals && <ApprovalsTable key="approvals" approvals={widgets.approvals} />,
  ].filter(Boolean)

  /** Columns that match what is actually there, so a gated card leaves no gap. */
  const columnsFor = (count: number): string =>
    count >= 3 ? 'xl:grid-cols-3' : count === 2 ? 'lg:grid-cols-2' : ''

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="Back Office" />

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="text-default-400 flex flex-wrap items-center gap-2 text-sm">
          <span className="border-default-200 bg-card inline-flex items-center gap-2 rounded-md border px-3 py-1.5">
            <Icon icon="calendar" className="size-4" />
            {context.week_label}
          </span>
          <span className="border-default-200 bg-card inline-flex items-center gap-2 rounded-md border px-3 py-1.5">
            <Icon icon="building" className="size-4" />
            {context.scoped ? `My ${context.property_count} properties` : `All ${context.property_count} properties`}
          </span>
        </div>

        {widgets.actions.length > 0 && <QuickActions actions={widgets.actions} />}
      </div>

      {widgets.alerts.map((alert) => (
        <AlertBanner key={alert.key} alert={alert} />
      ))}

      {widgets.headline.length > 0 && (
        <div className="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {widgets.headline.map((stat, i) => (
            <PulseStat key={i} stat={stat} />
          ))}
        </div>
      )}

      {widgets.pipeline && <PipelineStrip pipeline={widgets.pipeline} />}

      <div className="mb-4 grid gap-4 xl:grid-cols-3">
        {heroChart && (
          <div className="xl:col-span-2">
            <TrendChart trend={heroChart} />
          </div>
        )}
        <div className={heroChart ? '' : 'xl:col-span-3'}>
          <TasksCard tasks={widgets.tasks} />
        </div>
      </div>

      {liveOps.length > 0 && <div className={cn('mb-4 grid gap-4', columnsFor(liveOps.length))}>{liveOps}</div>}

      {worklists.length > 0 && (
        <div className={cn('mb-4 grid gap-4', worklists.length >= 2 ? 'lg:grid-cols-2' : '')}>{worklists}</div>
      )}

      {widgets.stats.length > 0 && (
        <div className="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {widgets.stats.map((stat, i) => (
            <StatCard key={i} stat={stat} />
          ))}
        </div>
      )}

      {(widgets.lists.length > 0 || widgets.decisions) && (
        <div className="grid gap-4 lg:grid-cols-2">
          {widgets.lists.map((widget, i) => (
            <ListCard key={i} widget={widget} />
          ))}
          {/* Recent activity moved up to the live-ops row, so this is where
              "Needs a decision soon" lands. */}
          {widgets.decisions && <DecisionsCard decisions={widgets.decisions} />}
        </div>
      )}
    </>
  )
}

export default Page
