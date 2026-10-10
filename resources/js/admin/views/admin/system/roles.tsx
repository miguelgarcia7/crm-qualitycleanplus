import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head } from '@inertiajs/react'
import { Fragment, useState } from 'react'
import ReferenceNav from './components/ReferenceNav'

type Role = { key: string; label: string; total: number }
type Permission = { key: string; label: string; roles: string[]; super_only: boolean; scope: string | null }
type Group = { name: string; permissions: Permission[] }

type Props = {
  roles: Role[]
  groups: Group[]
  total: number
  initial: { role: string | null; q: string }
}

const Page = ({ roles, groups, total, initial }: Props) => {
  const [query, setQuery] = useState(initial.q)
  const [highlight, setHighlight] = useState<string | null>(initial.role)
  const [open, setOpen] = useState<Set<string>>(() => new Set(groups.length > 0 ? [groups[0].name] : []))

  const words = query.toLowerCase().split(/\s+/).filter(Boolean)
  const searching = words.length > 0
  const matches = (p: Permission) => words.every((w) => `${p.label} ${p.key}`.toLowerCase().includes(w))

  const toggle = (name: string) =>
    setOpen((prev) => {
      const next = new Set(prev)
      if (next.has(name)) next.delete(name)
      else next.add(name)
      return next
    })

  const visibleGroups = groups
    .map((g) => ({ ...g, rows: searching ? g.permissions.filter(matches) : g.permissions }))
    .filter((g) => !searching || g.rows.length > 0)

  // The highlighted role's column is tinted all the way down.
  const col = (key: string) => (key === highlight ? 'bg-primary/10' : '')

  return (
    <>
      <Head title="Roles & Permissions" />
      <PageBreadcrumb title="System Reference" subtitle="Administration" />
      <ReferenceNav current="/admin/system/roles" />

      <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
        <p className="text-default-500 max-w-2xl">
          {total} permissions across {roles.length} roles. Super Admin has every permission, so it isn't shown as a column. Click a role to highlight it.
        </p>
        <div className="flex gap-2">
          <button type="button" className="btn border-default-300 text-default-700 hover:border-default-400 border" onClick={() => setOpen(new Set(groups.map((g) => g.name)))}>
            Expand all
          </button>
          <button type="button" className="btn border-default-300 text-default-700 hover:border-default-400 border" onClick={() => setOpen(new Set())}>
            Collapse all
          </button>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <div className="input-icon-group w-full max-w-md">
            <Icon icon="search" className="input-icon" />
            <label htmlFor="permission-search" className="sr-only">
              Search permissions
            </label>
            <input
              id="permission-search"
              type="search"
              className="form-input"
              placeholder="Search permissions, e.g. “invoice” or “approve”"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              autoComplete="off"
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[960px] border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th scope="col" className="text-default-400 border-default-300 w-80 border-b px-5 py-3 text-start text-xs font-semibold">
                  Permission
                </th>
                {roles.map((r) => (
                  <th key={r.key} scope="col" className={cn('border-default-300 border-b px-1 py-2', col(r.key))}>
                    <button
                      type="button"
                      onClick={() => setHighlight(r.key === highlight ? null : r.key)}
                      aria-pressed={r.key === highlight}
                      className={cn(
                        'flex w-full flex-col items-center rounded-md px-1 py-1.5 text-xs leading-tight font-semibold',
                        r.key === highlight ? 'bg-primary text-white' : 'text-default-600 hover:bg-default-100',
                      )}
                    >
                      <span>{r.label}</span>
                      <span className="mt-0.5 font-medium tabular-nums opacity-80">{r.total}</span>
                    </button>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {visibleGroups.map((g) => {
                const expanded = searching || open.has(g.name)
                return (
                  <Fragment key={g.name}>
                    <tr>
                      <th scope="rowgroup" className="bg-default-50 border-default-300 border-b p-0 text-start">
                        <button
                          type="button"
                          onClick={() => toggle(g.name)}
                          aria-expanded={expanded}
                          disabled={searching}
                          className="text-default-900 flex w-full items-center gap-2 px-5 py-2.5 text-start font-semibold"
                        >
                          <Icon icon="chevron-right" className={cn('text-default-400 size-4 transition-transform', expanded && 'rotate-90')} />
                          {g.name}
                          <span className="text-default-400 text-xs font-medium">{searching ? `${g.rows.length} of ${g.permissions.length}` : g.permissions.length}</span>
                        </button>
                      </th>
                      {roles.map((r) => {
                        const n = g.permissions.filter((p) => p.roles.includes(r.key)).length
                        const all = n === g.permissions.length
                        return (
                          <td
                            key={r.key}
                            className={cn(
                              'border-default-300 border-b text-center text-xs tabular-nums',
                              n === 0 ? 'bg-default-50 text-default-300' : all ? 'bg-success/15 text-success font-semibold' : 'bg-success/5 text-default-600',
                              r.key === highlight && 'ring-primary/30 ring-1 ring-inset',
                            )}
                          >
                            {n === 0 ? '–' : `${n}/${g.permissions.length}`}
                          </td>
                        )
                      })}
                    </tr>

                    {expanded &&
                      g.rows.map((p) => (
                        <tr key={p.key}>
                          <td className="border-default-100 border-b py-2.5 ps-11 pe-4">
                            <div className="text-default-800 flex flex-wrap items-center gap-2">
                              {p.label}
                              {p.super_only && <span className="badge badge-label bg-purple/15 text-purple">Super Admin only</span>}
                              {p.scope && <span className="badge badge-label bg-default-100 text-default-500">{p.scope}</span>}
                            </div>
                            <div className="text-default-400 font-mono text-xs">{p.key}</div>
                          </td>
                          {roles.map((r) => (
                            <td key={r.key} className={cn('border-default-100 border-b text-center', col(r.key))}>
                              {p.roles.includes(r.key) ? (
                                <Icon icon="check" className="text-success inline size-4.5" aria-label="Allowed" />
                              ) : (
                                <span className="text-default-300" aria-label="Not allowed">
                                  –
                                </span>
                              )}
                            </td>
                          ))}
                        </tr>
                      ))}
                  </Fragment>
                )
              })}
              {visibleGroups.length === 0 && (
                <tr>
                  <td colSpan={roles.length + 1} className="text-default-400 px-5 py-8 text-center">
                    No permission matches “{query}”.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <p className="text-default-400 text-sm">Read from this environment's permission settings. Changing what a role can do still happens in code; this page only shows it.</p>
    </>
  )
}

export default Page
