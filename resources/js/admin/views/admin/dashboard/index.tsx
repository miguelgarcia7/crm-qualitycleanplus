import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, usePage } from '@inertiajs/react'
import ActivityCard, { ActivityRow } from './widgets/ActivityCard'
import ClockedInCard, { ClockedInRow } from './widgets/ClockedInCard'
import DonutCard, { Donut } from './widgets/DonutCard'
import ListCard, { ListWidget } from './widgets/ListCard'
import QuickActions, { QuickAction } from './widgets/QuickActions'
import StatCard, { Stat } from './widgets/StatCard'
import TrendChart, { Trend } from './widgets/TrendChart'

type Widgets = {
  stats: Stat[]
  lists: ListWidget[]
  charts: Trend[]
  actions: QuickAction[]
  clockedIn: ClockedInRow[] | null
  donut: Donut | null
  activity: ActivityRow[] | null
}

type Props = { widgets: Widgets }

const greeting = () => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
}

const today = () =>
  new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })

const Page = ({ widgets }: Props) => {
  const user = (usePage().props as { auth?: { user?: { name?: string } } }).auth?.user
  const firstName = (user?.name ?? '').split(' ')[0]

  // First chart gets the wide hero slot; the donut (when present) sits beside it.
  const [heroChart, ...restCharts] = widgets.charts
  const sideCards = [
    widgets.clockedIn !== null && <ClockedInCard key="clocked-in" rows={widgets.clockedIn} />,
    widgets.activity !== null && <ActivityCard key="activity" rows={widgets.activity} />,
  ].filter(Boolean)

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="Back Office" />

      <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h4 className="text-lg font-bold">
            {greeting()}, {firstName}
          </h4>
          <p className="text-default-400 text-sm">{today()} — here&apos;s what needs your attention.</p>
        </div>
        {widgets.actions.length > 0 && <QuickActions actions={widgets.actions} />}
      </div>

      {widgets.stats.length > 0 && (
        <div className="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {widgets.stats.map((stat, i) => (
            <StatCard key={i} stat={stat} />
          ))}
        </div>
      )}

      {(heroChart || widgets.donut) && (
        <div className="mb-4 grid gap-4 xl:grid-cols-3">
          {heroChart && (
            <div className={widgets.donut ? 'xl:col-span-2' : 'xl:col-span-3'}>
              <TrendChart trend={heroChart} />
            </div>
          )}
          {widgets.donut && <DonutCard donut={widgets.donut} />}
        </div>
      )}

      {restCharts.length > 0 && (
        <div className="mb-4 grid gap-4 lg:grid-cols-2">
          {restCharts.map((trend, i) => (
            <TrendChart key={i} trend={trend} />
          ))}
        </div>
      )}

      {(widgets.lists.length > 0 || sideCards.length > 0) && (
        <div className="grid gap-4 lg:grid-cols-2">
          {sideCards}
          {widgets.lists.map((widget, i) => (
            <ListCard key={i} widget={widget} />
          ))}
        </div>
      )}
    </>
  )
}

export default Page
