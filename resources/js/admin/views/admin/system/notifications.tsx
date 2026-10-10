import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head } from '@inertiajs/react'
import { Fragment, useState } from 'react'
import ReferenceNav from './components/ReferenceNav'

type Role = { key: string; label: string }
type Notice = {
  id: string
  group: string
  name: string
  summary: string
  what: string
  when: string
  who: string
  classes: string[]
  in_app: boolean
  email: boolean
  can_mute: boolean
  mute_category: string | null
  audience: Record<string, 'always' | 'if_theirs'>
}

type Props = {
  roles: Role[]
  notices: Notice[]
  initial: string | null
}

// One channel chip; dashed when the person only gets it for something of theirs.
const Chip = ({ kind, conditional }: { kind: 'app' | 'mail'; conditional: boolean }) => (
  <span
    role="img"
    aria-label={`${kind === 'app' ? 'In-app' : 'Email'}${conditional ? ', only if it’s theirs' : ''}`}
    className={cn(
      'm-px inline-flex size-6 items-center justify-center rounded-md align-middle',
      kind === 'app' ? 'text-primary' : 'text-secondary',
      conditional ? 'border border-dashed border-current opacity-75' : kind === 'app' ? 'bg-primary/15' : 'bg-secondary/15',
    )}
  >
    <Icon icon={kind === 'app' ? 'bell' : 'mail'} className="size-3.5" />
  </span>
)

const Page = ({ roles, notices: notifications, initial }: Props) => {
  const [selectedId, setSelectedId] = useState(initial)
  const selected = notifications.find((n) => n.id === selectedId) ?? notifications[0]

  const groups = notifications.reduce<{ name: string; rows: Notice[] }[]>((acc, n) => {
    const last = acc[acc.length - 1]
    if (last && last.name === n.group) last.rows.push(n)
    else acc.push({ name: n.group, rows: [n] })
    return acc
  }, [])

  const stats = [
    { n: notifications.length, label: 'notification types' },
    { n: notifications.filter((n) => n.in_app && !n.email).length, label: 'in-app only' },
    { n: notifications.filter((n) => n.email).length, label: 'send an email' },
    { n: 0, label: 'by text message' },
  ]

  const recipients = (n: Notice) =>
    roles
      .filter((r) => n.audience[r.key])
      .map((r) => r.label + (n.audience[r.key] === 'if_theirs' ? ' (if theirs)' : ''))
      .join(', ')

  return (
    <>
      <Head title="Notifications" />
      <PageBreadcrumb title="System Reference" subtitle="Administration" />
      <ReferenceNav current="/admin/system/notifications" />

      <p className="text-default-500 mb-5 max-w-2xl">Every notice the system sends, who receives it and how. Click a row for the details.</p>

      <div className="mb-5 flex flex-wrap gap-3">
        {stats.map((s) => (
          <div key={s.label} className="card mb-0 min-w-36 px-4 py-3">
            <div className="text-default-900 text-xl font-semibold tabular-nums">{s.n}</div>
            <div className="text-default-400 text-sm">{s.label}</div>
          </div>
        ))}
      </div>

      <div className="text-default-500 mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
        <span className="inline-flex items-center gap-1.5">
          <Chip kind="app" conditional={false} /> In-app
        </span>
        <span className="inline-flex items-center gap-1.5">
          <Chip kind="mail" conditional={false} /> Email
        </span>
        <span className="inline-flex items-center gap-1.5">
          <Chip kind="app" conditional /> Only if they made the request, or it’s their own account
        </span>
        <span>Email is skipped for anyone without an email address.</span>
      </div>

      <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div className="card mb-0 overflow-x-auto">
          <table className="w-full min-w-[860px] border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th scope="col" className="text-default-400 border-default-300 w-64 border-b px-4 py-3 text-start text-xs font-semibold">
                  Notification
                </th>
                {roles.map((r) => (
                  <th key={r.key} scope="col" className="text-default-500 border-default-300 border-b px-1 py-3 text-xs leading-tight font-semibold">
                    {r.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {groups.map((g) => (
                <Fragment key={g.name}>
                  <tr>
                    <th colSpan={roles.length + 1} scope="rowgroup" className="bg-default-50 text-default-500 border-default-300 border-b px-4 py-2 text-start text-xs font-semibold tracking-wide uppercase">
                      {g.name}
                    </th>
                  </tr>
                  {g.rows.map((n) => {
                    const isSelected = n.id === selected?.id
                    return (
                      <tr key={n.id} className={cn(isSelected && 'bg-primary/10')}>
                        <td className="border-default-100 border-b p-0">
                          <button type="button" onClick={() => setSelectedId(n.id)} aria-pressed={isSelected} className="flex w-full flex-col px-4 py-2.5 text-start">
                            <span className="text-default-800 font-medium">{n.name}</span>
                            <span className="text-default-400 text-xs">{n.summary}</span>
                          </button>
                        </td>
                        {roles.map((r) => {
                          const mode = n.audience[r.key]
                          return (
                            <td key={r.key} className="border-default-100 border-b text-center whitespace-nowrap">
                              {mode && n.in_app && <Chip kind="app" conditional={mode === 'if_theirs'} />}
                              {mode && n.email && <Chip kind="mail" conditional={mode === 'if_theirs'} />}
                            </td>
                          )
                        })}
                      </tr>
                    )
                  })}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>

        {selected && (
          <aside aria-label="Notification details" className="card mb-0 xl:sticky xl:top-24">
            <div className="card-body flex flex-col gap-4">
              <div>
                <div className="text-default-400 text-xs font-semibold tracking-wide uppercase">{selected.group}</div>
                <h4 className="text-default-900 mt-0.5 text-lg font-semibold">{selected.name}</h4>
              </div>
              <p className="text-default-600">{selected.what}</p>
              <dl className="grid grid-cols-[110px_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                <dt className="text-default-400">Sent when</dt>
                <dd className="text-default-800">{selected.when}</dd>
                <dt className="text-default-400">Goes to</dt>
                <dd className="text-default-800">
                  {selected.who}
                  <div className="text-default-400 mt-0.5 text-xs">{recipients(selected)}</div>
                </dd>
                <dt className="text-default-400">Delivered</dt>
                <dd className="text-default-800">{selected.in_app && selected.email ? 'In-app and email' : selected.email ? 'Email only' : 'In-app only'}</dd>
                <dt className="text-default-400">Can be muted</dt>
                <dd className="text-default-800">{selected.can_mute ? `Yes, with “${selected.mute_category}” in My Profile → Notifications` : 'No'}</dd>
                <dt className="text-default-400">In the code</dt>
                <dd className="text-default-600 font-mono text-xs break-words">{selected.classes.join(' + ')}</dd>
              </dl>
            </div>
          </aside>
        )}
      </div>
    </>
  )
}

export default Page
