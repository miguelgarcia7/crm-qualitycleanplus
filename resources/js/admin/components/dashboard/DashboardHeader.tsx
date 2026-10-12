import PageMetaData from '@/components/PageMetaData'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'
import { ReactNode } from 'react'

export type DashboardTab = { key: string; label: string; href: string }

type Props = {
  greeting: string
  subtitle: ReactNode
  /** The one or two things this role starts from the dashboard, or a period switch. */
  aside?: ReactNode
  tabs?: DashboardTab[]
  current?: string
}

/** Greeting, scope line and, for roles with more than one view, the tabs under it. */
const DashboardHeader = ({ greeting, subtitle, aside, tabs = [], current }: Props) => (
  <>
    <Head title="Dashboard" />
    <PageMetaData title="Dashboard" />

    <div className={cn('flex flex-wrap items-end justify-between gap-4 pt-4', tabs.length > 1 ? 'mb-0' : 'mb-6')}>
      <div>
        <h1 className="page-main-title">{greeting}</h1>
        <p className="text-default-500 mt-1">{subtitle}</p>
      </div>
      {aside}
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

export default DashboardHeader
