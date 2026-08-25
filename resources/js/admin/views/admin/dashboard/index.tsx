import Icon from '@/components/wrappers/Icon'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head } from '@inertiajs/react'
import ActivityCard, { ActivityRow } from './widgets/ActivityCard'
import AlertBanner, { PulseAlert } from './widgets/AlertBanner'
import ApprovalsTable, { Approvals } from './widgets/ApprovalsTable'
import DecisionsCard, { Decisions } from './widgets/DecisionsCard'
import ListCard, { ListWidget } from './widgets/ListCard'
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
  onTheClock: OnTheClock | null
  decisions: Decisions | null
}

type Props = { widgets: Widgets }

const Page = ({ widgets }: Props) => {
  const { context } = widgets
  // The revenue-vs-payouts chart leads; anything else drops below.
  const [heroChart, ...restCharts] = widgets.charts

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

      <div className="mb-4 grid gap-4 xl:grid-cols-3">
        {widgets.approvals && (
          <div className="xl:col-span-2">
            <ApprovalsTable approvals={widgets.approvals} />
          </div>
        )}
        <div className={`flex flex-col gap-4 ${widgets.approvals ? '' : 'xl:col-span-3'}`}>
          {widgets.onTheClock && <OnTheClockCard data={widgets.onTheClock} />}
          {widgets.decisions && <DecisionsCard decisions={widgets.decisions} />}
        </div>
      </div>

      {restCharts.length > 0 && (
        <div className="mb-4 grid gap-4 lg:grid-cols-2">
          {restCharts.map((trend, i) => (
            <TrendChart key={i} trend={trend} />
          ))}
        </div>
      )}

      {widgets.stats.length > 0 && (
        <div className="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {widgets.stats.map((stat, i) => (
            <StatCard key={i} stat={stat} />
          ))}
        </div>
      )}

      {(widgets.lists.length > 0 || widgets.activity !== null) && (
        <div className="grid gap-4 lg:grid-cols-2">
          {widgets.lists.map((widget, i) => (
            <ListCard key={i} widget={widget} />
          ))}
          {widgets.activity !== null && <ActivityCard rows={widgets.activity} />}
        </div>
      )}
    </>
  )
}

export default Page
