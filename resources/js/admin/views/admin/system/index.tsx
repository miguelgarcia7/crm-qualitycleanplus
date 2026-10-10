import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import ReferenceNav from './components/ReferenceNav'

type Permission = { key: string; label: string; area: string; roles: string[] }
type Automation = { name: string; cadence: string; next_run: string }
type NoticeSummary = { id: string; name: string; group: string; who: string }

type Props = {
  stats: { permissions: number; roles: number; automations: number; notifications: number; notifications_email: number }
  permissions: Permission[]
  automations: Automation[]
  notices: NoticeSummary[]
  timezone: string
}

type Result = { kind: 'Permission' | 'Notification' | 'Automation'; title: string; detail: string; tag: string; haystack: string; href: string | null }

const SUGGESTIONS = ['approve PTO', 'contracts', 'mark paid', 'Recruiter', 'pay weeks']
const MAX_RESULTS = 8

// Each topic becomes its own page; until then its card says what it will hold.
const topics = (stats: Props['stats']) => [
  {
    title: 'Roles & permissions',
    text: 'What each role can see and do, side by side.',
    icon: 'key',
    tone: 'bg-primary/15 text-primary',
    status: null,
    href: '/admin/system/roles',
    stats: [
      { n: stats.permissions, label: 'permissions' },
      { n: stats.roles, label: 'roles' },
    ],
  },
  {
    title: 'Notifications',
    text: 'Every in-app notice and email: who gets it, and whether it can be muted.',
    icon: 'bell',
    tone: 'bg-secondary/15 text-secondary',
    status: null,
    href: '/admin/system/notifications',
    stats: [
      { n: stats.notifications, label: 'types' },
      { n: stats.notifications_email, label: 'send email' },
    ],
  },
  {
    title: 'Automations',
    text: 'What runs by itself, and when, in Chicago time.',
    icon: 'clock',
    tone: 'bg-info/15 text-info',
    status: 'Coming soon',
    href: null,
    stats: [{ n: stats.automations, label: 'scheduled tasks' }],
  },
  {
    title: 'Workflows',
    text: 'Terminations, transfers, pay increases, staffing, supplies and PTO: who starts, who approves, what happens.',
    icon: 'route',
    tone: 'bg-default-200 text-default-500',
    status: 'Planned',
    href: null,
    stats: [],
  },
  {
    title: 'Status lifecycles',
    text: 'How a timesheet becomes an invoice and gets paid, and how a work order opens and closes.',
    icon: 'timeline',
    tone: 'bg-default-200 text-default-500',
    status: 'Planned',
    href: null,
    stats: [],
  },
  {
    title: 'System health',
    text: 'Which email service, file storage and job queue are in use. Super Admin only.',
    icon: 'activity',
    tone: 'bg-default-200 text-default-500',
    status: 'Planned',
    href: null,
    stats: [],
  },
]

const Page = ({ stats, permissions, automations, notices, timezone }: Props) => {
  const [query, setQuery] = useState('')

  const index = useMemo<Result[]>(
    () => [
      ...permissions.map((p) => {
        const who = p.roles.length > 0 ? p.roles.join(', ') : 'Super Admin only'
        return {
          kind: 'Permission' as const,
          title: p.label,
          detail: who,
          tag: p.area,
          haystack: [p.label, p.key, p.area, who].join(' ').toLowerCase(),
          href: `/admin/system/roles?q=${encodeURIComponent(p.key)}`,
        }
      }),
      ...notices.map((n) => ({
        kind: 'Notification' as const,
        title: n.name,
        detail: n.who,
        tag: n.group,
        haystack: [n.name, n.group, n.who, 'notification notice email alert'].join(' ').toLowerCase(),
        href: `/admin/system/notifications?n=${encodeURIComponent(n.id)}`,
      })),
      ...automations.map((a) => ({
        kind: 'Automation' as const,
        title: a.name,
        detail: `${a.cadence} (${timezone}). Next: ${a.next_run}`,
        tag: 'Scheduled task',
        haystack: [a.name, a.cadence, 'automation scheduled task'].join(' ').toLowerCase(),
        href: null,
      })),
    ],
    [permissions, notices, automations, timezone],
  )

  const words = query.toLowerCase().split(/\s+/).filter(Boolean)
  const matches = words.length === 0 ? [] : index.filter((r) => words.every((w) => r.haystack.includes(w)))

  return (
    <>
      <Head title="System Reference" />
      <PageBreadcrumb title="System Reference" subtitle="Administration" />
      <ReferenceNav current="/admin/system" />

      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p className="text-default-500 max-w-2xl">
          How QCP is set up: who can do what, who gets notified and what runs on its own. Read from the app itself, so it always matches what's deployed.
        </p>
        <span className="badge badge-label bg-primary/15 text-primary">Super Admin and Admin only</span>
      </div>

      <div className="card mb-5">
        <div className="card-body p-5">
          <label htmlFor="system-lookup" className="form-label font-semibold">
            Look something up
          </label>
          <div className="input-icon-group">
            <Icon icon="search" className="input-icon" />
            <input
              id="system-lookup"
              type="search"
              className="form-input"
              placeholder="Try “approve PTO”, “contracts” or a role like “Recruiter”"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              autoComplete="off"
            />
          </div>

          {words.length === 0 && (
            <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
              <span className="text-default-400">Examples:</span>
              {SUGGESTIONS.map((s) => (
                <button key={s} type="button" onClick={() => setQuery(s)} className="badge badge-label bg-default-100 text-default-600 hover:bg-primary hover:text-white">
                  {s}
                </button>
              ))}
            </div>
          )}

          {words.length > 0 && matches.length === 0 && (
            <p className="text-default-400 mt-3 text-sm">Nothing matches “{query}”. Try a shorter word, like “invoice” or “PTO”.</p>
          )}

          {matches.length > 0 && (
            <>
              <ul className="mt-3 flex flex-col gap-2">
                {matches.slice(0, MAX_RESULTS).map((r) => (
                  <li key={`${r.kind}-${r.title}`} className="bg-default-50 flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-md px-3 py-2.5">
                    <span className={cn('w-24 shrink-0 text-xs font-semibold uppercase', r.kind === 'Permission' ? 'text-primary' : r.kind === 'Notification' ? 'text-secondary' : 'text-info')}>{r.kind}</span>
                    {r.href ? (
                      <Link href={r.href} className="text-default-800 hover:text-primary font-medium underline-offset-2 hover:underline">
                        {r.title}
                      </Link>
                    ) : (
                      <span className="text-default-800 font-medium">{r.title}</span>
                    )}
                    <span className="text-default-500 text-sm">{r.detail}</span>
                    <span className="text-default-400 ms-auto text-xs">{r.tag}</span>
                  </li>
                ))}
              </ul>
              {matches.length > MAX_RESULTS && (
                <p className="text-default-400 mt-2 text-sm">
                  Showing {MAX_RESULTS} of {matches.length} matches. Add another word to narrow it down.
                </p>
              )}
            </>
          )}
        </div>
      </div>

      <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        {topics(stats).map((t) => {
          const body = (
            <div className="card-body flex h-full flex-col gap-4 p-5">
              <div className="flex items-center justify-between gap-3">
                <div className={cn('flex size-10 items-center justify-center rounded-lg', t.tone)}>
                  <Icon icon={t.icon} className="size-5" />
                </div>
                {t.status ? (
                  <span className={cn('badge badge-label', t.status === 'Planned' ? 'bg-default-100 text-default-500' : 'bg-warning/15 text-warning')}>{t.status}</span>
                ) : (
                  <Icon icon="arrow-right" className="text-default-400 size-5" />
                )}
              </div>
              <div>
                <h4 className="text-default-800 mb-1 text-base font-semibold">{t.title}</h4>
                <p className="text-default-500 text-sm">{t.text}</p>
              </div>
              {t.stats.length > 0 && (
                <div className="border-default-100 mt-auto flex gap-6 border-t pt-3">
                  {t.stats.map((s) => (
                    <div key={s.label}>
                      <div className="text-default-800 text-xl font-semibold tabular-nums">{s.n}</div>
                      <div className="text-default-400 text-xs">{s.label}</div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )
          return t.href ? (
            <Link key={t.title} href={t.href} className="card hover:border-primary/40 mb-0 h-full transition-colors">
              {body}
            </Link>
          ) : (
            <div key={t.title} className={cn('card mb-0 h-full', t.status === 'Planned' && 'border-default-300 border border-dashed bg-transparent shadow-none')}>
              {body}
            </div>
          )
        })}
      </div>
    </>
  )
}

export default Page
