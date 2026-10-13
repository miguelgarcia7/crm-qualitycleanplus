import SidePanel from '@/components/SidePanel'
import { cn } from '@/utils/helpers'
import { router, useForm } from '@inertiajs/react'
import { FormEvent, ReactNode, useState } from 'react'

export type AccessPayload = {
  roles: string[]
  role_labels: string[]
  surface: string
  all_properties: boolean
  properties: string[]
  has_password: boolean
  invited_at: string | null
  can_change_roles: boolean
  can_resend: boolean
  options: { key: string; label: string; does: string }[]
}

export type BookPayload = {
  contractors: { id: number; name: string; job: string | null }[]
  properties: string[]
  open_tasks: number
  can_move: boolean
  recruiters: { id: number; name: string }[]
}

export type RecruiterOption = { id: number; name: string }

const Label = ({ children }: { children: ReactNode }) => <div className="text-default-500 text-xs font-semibold tracking-wide uppercase">{children}</div>

const Card = ({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) => (
  <section className="card mb-0" aria-label={title}>
    <div className="card-body p-5">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-default-900 text-lg font-semibold">{title}</h2>
        {action}
      </div>
      {children}
    </div>
  </section>
)

const Check = ({ checked, onChange, title, note, disabled }: { checked: boolean; onChange: () => void; title: string; note?: string; disabled?: boolean }) => (
  <label
    className={cn(
      'grid cursor-pointer grid-cols-[1.25rem_minmax(0,1fr)] items-start gap-2.5 rounded-lg border px-3 py-2.5',
      checked ? 'border-primary bg-primary/5' : 'border-default-200',
      disabled && 'cursor-not-allowed opacity-60',
    )}>
    <input type="checkbox" className="form-checkbox mt-0.5" checked={checked} onChange={onChange} disabled={disabled} />
    <span>
      <span className="text-default-900 block font-semibold">{title}</span>
      {note && <span className="text-default-500 block text-sm">{note}</span>}
    </span>
  </label>
)

/** Roles, whether they've signed in, and their properties, with the role editor. */
export const AccessCard = ({ personId, name, access }: { personId: number; name: string; access: AccessPayload }) => {
  const [editing, setEditing] = useState(false)
  const form = useForm<{ roles: string[] }>({ roles: access.roles.filter((r) => access.options.some((o) => o.key === r)) })
  const first = name.split(' ')[0]
  const toggle = (key: string) => form.setData('roles', form.data.roles.includes(key) ? form.data.roles.filter((r) => r !== key) : [...form.data.roles, key])

  const save = (e?: FormEvent) => {
    e?.preventDefault()
    form.put(`/admin/people/${personId}/roles`, { preserveScroll: true, onSuccess: () => setEditing(false) })
  }

  return (
    <Card
      title="Sign-in and access"
      action={
        access.can_change_roles && (
          <button type="button" className="btn bg-primary/15 text-primary hover:bg-primary/25" onClick={() => setEditing(true)}>
            Change roles
          </button>
        )
      }>
      <div className="grid gap-5 sm:grid-cols-3">
        <div>
          <Label>Roles</Label>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {access.role_labels.map((r) => (
              <span key={r} className="badge badge-label bg-primary/15 text-default-900">
                {r}
              </span>
            ))}
          </div>
          <p className="text-default-500 mt-2 mb-0 text-sm">
            {access.surface} · {access.all_properties ? 'every property' : 'only their own properties'}
          </p>
        </div>
        <div>
          <Label>Sign-in</Label>
          <p className="text-default-900 mt-2 mb-0 font-semibold">{access.has_password ? 'Has set a password' : 'Hasn’t set a password yet'}</p>
          {access.invited_at && <p className="text-default-500 mt-1 mb-0 text-sm">{access.has_password ? "Account created" : "Invited"} {access.invited_at}</p>}
        </div>
        <div>
          <Label>Properties</Label>
          <p className="text-default-900 mt-2 mb-0 font-semibold">{access.all_properties ? 'All properties' : access.properties.join(', ') || 'None yet'}</p>
          {!access.all_properties && <p className="text-default-500 mt-1 mb-0 text-sm">Change on each property’s Access tab</p>}
        </div>
      </div>

      {access.can_resend && (
        <div className="bg-warning/10 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg px-4 py-3">
          <span className="text-sm">
            {first} hasn’t set a password. The invitation link works for 3 days; send a new one if it ran out.
          </span>
          <button type="button" className="btn bg-primary/15 text-primary hover:bg-primary/25" onClick={() => router.post(`/admin/people/${personId}/invitation`, {}, { preserveScroll: true })}>
            Send a new invitation
          </button>
        </div>
      )}

      <p className="text-default-500 border-default-200 mt-4 mb-0 border-t pt-3 text-sm">What each role can do is on System Reference → Roles.</p>

      {editing && (
        <SidePanel
          title="Change roles"
          subtitle={`${name}. They get everything each ticked role can do.`}
          onClose={() => setEditing(false)}
          footer={
            <div className="flex justify-end gap-2">
              <button type="button" className="btn bg-light text-default-900" onClick={() => setEditing(false)}>
                Cancel
              </button>
              <button type="button" className="btn bg-primary hover:bg-primary-hover text-white" disabled={form.processing || form.data.roles.length === 0} onClick={() => save()}>
                Save roles
              </button>
            </div>
          }>
          <form onSubmit={save} className="flex flex-col gap-2.5 p-5">
            <Label>Office staff · back office</Label>
            {access.options.map((o) => (
              <Check key={o.key} checked={form.data.roles.includes(o.key)} onChange={() => toggle(o.key)} title={o.label} note={o.does} />
            ))}
            <Label>Hotel · QC Minute</Label>
            <Check checked={false} onChange={() => {}} disabled title="Property Manager" note="Can’t be combined with office roles. A property manager works for the hotel and never sees pay." />
            {form.errors.roles && (
              <div role="alert" className="bg-warning/15 rounded-lg px-4 py-3 text-sm">
                {form.errors.roles}
              </div>
            )}
            <p className="text-default-500 mb-0 text-sm">Admin access is set up outside the app. You can’t change your own roles.</p>
          </form>
        </SidePanel>
      )}
    </Card>
  )
}

/** A recruiter's contractors, and handing them to another recruiter. */
export const BookCard = ({ personId, name, book }: { personId: number; name: string; book: BookPayload }) => {
  const [moving, setMoving] = useState(false)
  const first = name.split(' ')[0]
  const form = useForm<{ to: number | ''; contractor_ids: number[]; give_properties: boolean; remove_properties: boolean }>({
    to: book.recruiters[0]?.id ?? '',
    contractor_ids: book.contractors.map((c) => c.id),
    give_properties: book.properties.length > 0,
    remove_properties: false,
  })
  const toName = book.recruiters.find((r) => r.id === form.data.to)?.name.split(' ')[0] ?? 'them'
  const toggle = (id: number) =>
    form.setData('contractor_ids', form.data.contractor_ids.includes(id) ? form.data.contractor_ids.filter((c) => c !== id) : [...form.data.contractor_ids, id])

  const move = () =>
    form.post(`/admin/people/${personId}/contractors/move`, {
      preserveScroll: true,
      onSuccess: () => setMoving(false),
    })

  return (
    <Card
      title={`${first}’s contractors`}
      action={
        book.can_move &&
        book.contractors.length > 0 &&
        book.recruiters.length > 0 && (
          <button type="button" className="btn border-default-300 hover:border-default-400 border" onClick={() => setMoving(true)}>
            Move to another recruiter
          </button>
        )
      }>
      <p className="text-default-500 mb-3 text-sm">
        Contractors whose primary recruiter is {first}. Hours problems and direct-hire alerts for them go to {first}.
      </p>
      {book.contractors.length ? (
        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
          {book.contractors.map((c) => (
            <a key={c.id} href={`/admin/people/${c.id}`} className="border-default-200 hover:border-default-300 rounded-lg border px-3 py-2.5">
              <span className="text-default-900 block font-semibold">{c.name}</span>
              <span className="text-default-500 block text-sm">{c.job ?? 'No active work order'}</span>
            </a>
          ))}
        </div>
      ) : (
        <p className="text-default-500 mb-0">No contractors yet.</p>
      )}

      {moving && (
        <SidePanel
          title="Move contractors to another recruiter"
          subtitle={`From ${name}. Their work orders, rates and hours don’t change.`}
          onClose={() => setMoving(false)}
          footer={
            <div className="flex justify-end gap-2">
              <button type="button" className="btn bg-light text-default-900" onClick={() => setMoving(false)}>
                Cancel
              </button>
              <button type="button" className="btn bg-primary hover:bg-primary-hover text-white" disabled={form.processing || form.data.contractor_ids.length === 0 || form.data.to === ''} onClick={move}>
                Move {form.data.contractor_ids.length} to {toName}
              </button>
            </div>
          }>
          <div className="flex flex-col gap-5 p-5">
            <div>
              <label htmlFor="move-to" className="form-label">
                Move to
              </label>
              <select id="move-to" className="form-input" value={form.data.to} onChange={(e) => form.setData('to', Number(e.target.value))}>
                {book.recruiters.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
              {form.errors.to && <p className="text-danger mt-1 text-sm">{form.errors.to}</p>}
            </div>

            <div className="flex flex-col gap-2">
              <div className="flex items-baseline justify-between">
                <Label>Contractors</Label>
                <span className="text-default-500 text-sm">
                  {form.data.contractor_ids.length} of {book.contractors.length}
                </span>
              </div>
              {book.contractors.map((c) => (
                <Check key={c.id} checked={form.data.contractor_ids.includes(c.id)} onChange={() => toggle(c.id)} title={c.name} note={c.job ?? undefined} />
              ))}
            </div>

            {book.properties.length > 0 && (
              <div className="flex flex-col gap-2">
                <Label>{first}’s properties</Label>
                <Check
                  checked={form.data.give_properties}
                  onChange={() => form.setData('give_properties', !form.data.give_properties)}
                  title={`Give ${toName} ${book.properties.join(', ')} too`}
                  note="They’ll see its timesheets, work orders and the hotel’s requests."
                />
                <Check
                  checked={form.data.remove_properties}
                  onChange={() => form.setData('remove_properties', !form.data.remove_properties)}
                  title={`Take ${book.properties.length === 1 ? 'it' : 'them'} off ${first}`}
                  note="Leave it unticked if they’ll share for a while."
                />
              </div>
            )}

            {book.open_tasks > 0 && (
              <div className="bg-light rounded-lg px-4 py-3 text-sm">
                <span className="text-default-900 font-semibold">
                  {book.open_tasks} open {book.open_tasks === 1 ? 'task goes' : 'tasks go'} with them.
                </span>{' '}
                Their hours problems move to {toName}’s My Tasks.
              </div>
            )}
            <p className="text-default-500 mb-0 text-sm">Each contractor’s History shows the change and who made it.</p>
          </div>
        </SidePanel>
      )}
    </Card>
  )
}

/** "Change" beside a contractor's recruiter. */
export const RecruiterChange = ({ personId, name, current, options }: { personId: number; name: string; current: string | null; options: RecruiterOption[] }) => {
  const [open, setOpen] = useState(false)
  const choices = options.filter((o) => o.name !== current)
  const form = useForm<{ to: number | '' }>({ to: choices[0]?.id ?? '' })

  if (choices.length === 0) return null

  return (
    <>
      <button type="button" className="text-primary text-sm font-semibold hover:underline" onClick={() => setOpen(true)}>
        Change
      </button>
      {open && (
        <SidePanel
          title="Change recruiter"
          subtitle={`${name}${current ? `, now with ${current}` : ''}`}
          onClose={() => setOpen(false)}
          footer={
            <div className="flex justify-end gap-2">
              <button type="button" className="btn bg-light text-default-900" onClick={() => setOpen(false)}>
                Cancel
              </button>
              <button
                type="button"
                className="btn bg-primary hover:bg-primary-hover text-white"
                disabled={form.processing || form.data.to === ''}
                onClick={() => form.put(`/admin/people/${personId}/recruiter`, { preserveScroll: true, onSuccess: () => setOpen(false) })}>
                Save
              </button>
            </div>
          }>
          <div className="flex flex-col gap-3 p-5">
            <label htmlFor="new-recruiter" className="form-label mb-0">
              New recruiter
            </label>
            <select id="new-recruiter" className="form-input" value={form.data.to} onChange={(e) => form.setData('to', Number(e.target.value))}>
              {choices.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.name}
                </option>
              ))}
            </select>
            {form.errors.to && <p className="text-danger text-sm">{form.errors.to}</p>}
            <p className="text-default-500 mb-0 text-sm">Only the recruiter changes: work orders, rates and hotel stay the same. To move them to another hotel, use Transfer.</p>
          </div>
        </SidePanel>
      )}
    </>
  )
}
