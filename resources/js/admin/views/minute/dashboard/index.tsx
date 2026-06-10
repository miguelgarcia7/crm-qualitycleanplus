import PageBreadcrumb from '@/components/PageBreadcrumb'
import StatCard, { Stat } from '@/views/admin/dashboard/widgets/StatCard'
import ListCard, { ListWidget } from '@/views/admin/dashboard/widgets/ListCard'
import TrendChart, { Trend } from '@/views/admin/dashboard/widgets/TrendChart'
import { Head, Link, usePage } from '@inertiajs/react'

type Widgets = { stats: Stat[]; lists: ListWidget[]; charts: Trend[] }
type Props = { widgets: Widgets | null }

const cards = [
  { href: '/timesheets', title: 'Timesheets', desc: 'Review and approve weekly hours.' },
  { href: '/pay-increases', title: 'Pay Increases', desc: 'Request a raise for a contractor.' },
  { href: '/staffing-requests', title: 'Staffing Requests', desc: 'Ask for more contractors at your property.' },
  { href: '/my-info', title: 'My Info', desc: 'Request a change to your name, email, or phone.' },
]

const Page = ({ widgets }: Props) => {
  const props = usePage().props as { auth?: { user?: { name?: string }; permissions?: string[] } }
  const user = props.auth?.user
  const canReadKb = (props.auth?.permissions ?? []).includes('kb.articles.view')

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="QC Minute" />

      <div className="card rounded-2xl">
        <div className="card-body p-6">
          <h4 className="mb-1 text-base font-bold">Welcome back, {user?.name}</h4>
          <p className="text-default-400">Approve timesheets and request pay increases for your contractors.</p>
        </div>
      </div>

      {widgets && (
        <>
          {widgets.stats.length > 0 && (
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              {widgets.stats.map((stat, i) => (
                <StatCard key={i} stat={stat} />
              ))}
            </div>
          )}
          {widgets.charts.length > 0 && (
            <div className="mt-4 grid gap-4">
              {widgets.charts.map((trend, i) => (
                <TrendChart key={i} trend={trend} />
              ))}
            </div>
          )}
          {widgets.lists.length > 0 && (
            <div className="mt-4 grid gap-4">
              {widgets.lists.map((widget, i) => (
                <ListCard key={i} widget={widget} />
              ))}
            </div>
          )}
        </>
      )}

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        {(canReadKb ? [...cards, { href: '/kb', title: 'Knowledge Base', desc: 'Guides and answers for day-to-day work.' }] : cards).map((card) => (
          <Link key={card.href} href={card.href} className="card rounded-2xl transition hover:shadow-lg">
            <div className="card-body p-6">
              <h5 className="font-semibold">{card.title}</h5>
              <p className="text-default-400 text-sm">{card.desc}</p>
              <p className="text-primary mt-2 text-sm">Open →</p>
            </div>
          </Link>
        ))}
      </div>
    </>
  )
}

export default Page
