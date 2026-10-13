import PageMetaData from '@/components/PageMetaData'
import { cn } from '@/utils/helpers'
import { Head, Link, usePage } from '@inertiajs/react'
import { ReactNode } from 'react'

export type DashboardTab = { key: string; label: string; href: string }

/** Sent with a role dashboard when the person holds more than one office role. */
type DashboardSwitch = { current: string; options: DashboardTab[] } | null

type Props = {
  greeting: string
  subtitle: ReactNode
  /** The one or two things this role starts from the dashboard, or a period switch. */
  aside?: ReactNode
  tabs?: DashboardTab[]
  current?: string
}

/** Which role's dashboard, for someone with two or more office roles. */
const RoleSwitch = ({ value }: { value: NonNullable<DashboardSwitch> }) => (
  <div role="group" aria-label="Dashboard for" className="bg-default-100 inline-flex rounded-lg p-1">
    {value.options.map((o) => (
      <Link
        key={o.key}
        href={o.href}
        aria-current={o.key === value.current ? 'page' : undefined}
        className={cn(
          'inline-flex min-h-10 items-center rounded-md px-3.5 text-sm font-semibold whitespace-nowrap',
          o.key === value.current ? 'bg-card text-default-900 shadow-sm' : 'text-default-500 hover:text-default-900',
        )}>
        {o.label}
      </Link>
    ))}
  </div>
)

/** Greeting, scope line and, for roles with more than one view, the tabs under it. */
const DashboardHeader = ({ greeting, subtitle, aside, tabs = [], current }: Props) => {
  const roleSwitch = (usePage().props as { dashboard_switch?: DashboardSwitch }).dashboard_switch ?? null

  return (
  <>
    <Head title="Dashboard" />
    <PageMetaData title="Dashboard" />

    <div className={cn('flex flex-wrap items-end justify-between gap-4 pt-4', tabs.length > 1 ? 'mb-0' : 'mb-6')}>
      <div>
        <h1 className="page-main-title">{greeting}</h1>
        <p className="text-default-500 mt-1">{subtitle}</p>
      </div>
      {(aside || roleSwitch) && (
        <div className="flex flex-wrap items-center gap-3">
          {roleSwitch && <RoleSwitch value={roleSwitch} />}
          {aside}
        </div>
      )}
    </div>

    {tabs.length > 1 && (
      <nav className="border-default-300 mt-4 mb-6 flex flex-wrap gap-1 border-b" aria-label="Dashboard views">
        {tabs.map((tab) => (
          <Link
            key={tab.key}
            href={tab.href}
            aria-current={tab.key === current ? 'page' : undefined}
            preserveScroll
            className={cn(
              '-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 font-medium focus:outline-hidden',
              tab.key === current ? 'border-primary text-default-900' : 'text-default-500 hover:text-default-900 border-transparent',
            )}>
            {tab.label}
          </Link>
        ))}
      </nav>
    )}
  </>
  )
}

export default DashboardHeader
