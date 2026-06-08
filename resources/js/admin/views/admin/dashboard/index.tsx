import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, usePage } from '@inertiajs/react'
import StatCard, { Stat } from './widgets/StatCard'
import ListCard, { ListWidget } from './widgets/ListCard'
import TrendChart, { Trend } from './widgets/TrendChart'

type Widgets = { stats: Stat[]; lists: ListWidget[]; charts: Trend[] }
type Props = { widgets: Widgets }

const Page = ({ widgets }: Props) => {
  const user = (usePage().props as { auth?: { user?: { name?: string } } }).auth?.user

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="Back Office" />

      <div className="mb-4">
        <h4 className="text-base font-bold">Welcome back, {user?.name}</h4>
        <p className="text-default-400 text-sm">Here's what needs your attention.</p>
      </div>

      {widgets.stats.length > 0 && (
        <div className="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {widgets.stats.map((stat, i) => (
            <StatCard key={i} stat={stat} />
          ))}
        </div>
      )}

      {widgets.charts.length > 0 && (
        <div className="mb-4 grid gap-4 lg:grid-cols-2">
          {widgets.charts.map((trend, i) => (
            <TrendChart key={i} trend={trend} />
          ))}
        </div>
      )}

      {widgets.lists.length > 0 && (
        <div className="grid gap-4 lg:grid-cols-2">
          {widgets.lists.map((widget, i) => (
            <ListCard key={i} widget={widget} />
          ))}
        </div>
      )}
    </>
  )
}

export default Page
