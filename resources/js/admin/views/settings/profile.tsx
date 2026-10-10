import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { ChangeEvent, FormEvent, useEffect, useRef, useState } from 'react'

// --- Types -------------------------------------------------------------------

type PersonInfo = {
  name: string
  email: string
  phone: string | null
  status: string
  is_active: boolean
  hire_date: string | null
  joined: string | null
  avatar: string | null
}

type NotificationCategory = {
  value: string
  label: string
  description: string
  /** Muting this silences email as well as the bell. */
  emails: boolean
}

type NotificationSettings = {
  muted: string[]
  categories: NotificationCategory[]
}

type Access = {
  roles: string[]
  is_super_admin: boolean
  properties: string[]
  all_properties: boolean
  can_count: number
  total: number
  areas: { name: string; can: string[]; cant: string[] }[]
  notices: { name: string; summary: string; only_if_theirs: boolean; in_app: boolean; email: boolean; muted: boolean }[]
  has_email: boolean
  reference_url: string | null
}

type Props = {
  person: PersonInfo
  roles: string[]
  notificationSettings: NotificationSettings
  access: Access
}

// --- Shared bits (HRM identity-card pattern, as on property detail) ----------

const FactRow = ({ icon, label, children }: { icon: string; label: string; children: React.ReactNode }) => (
  <div className="flex items-center gap-3">
    <div>
      <div className="btn btn-icon bg-light size-8!">
        <Icon icon={icon} className="text-secondary text-lg" />
      </div>
    </div>
    <p className="text-sm">
      {label} <span className="text-dark font-semibold">{children}</span>
    </p>
  </div>
)

const initialsOf = (name: string) =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

// --- Avatar (photo or initials, with self-serve upload) ----------------------

const Avatar = ({ person }: { person: PersonInfo }) => {
  const base = useSettingsBase()
  const fileInput = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const pick = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setError(null)
    router.post(
      `${base}/avatar`,
      { avatar: file },
      {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => setUploading(true),
        onFinish: () => {
          setUploading(false)
          if (fileInput.current) fileInput.current.value = ''
        },
        onError: (errors) => setError(errors.avatar ?? 'Upload failed.'),
      },
    )
  }

  const remove = () => {
    confirmAction({
      title: 'Remove photo',
      message: <>Remove your profile photo? Your initials are shown instead.</>,
      confirmLabel: 'Remove',
      onConfirm: () => router.delete(`${base}/avatar`, { preserveScroll: true }),
    })
  }

  return (
    <div className="flex flex-col items-center gap-1.5">
      <div className="group relative">
        {person.avatar ? (
          <img src={person.avatar} alt={person.name} className="size-18 shrink-0 rounded-full object-cover" />
        ) : (
          <div className="bg-primary/10 text-primary flex size-18 shrink-0 items-center justify-center rounded-full text-xl font-semibold">
            {initialsOf(person.name)}
          </div>
        )}
        <button
          type="button"
          onClick={() => fileInput.current?.click()}
          disabled={uploading}
          title={person.avatar ? 'Change photo' : 'Add photo'}
          className="bg-primary hover:bg-primary-hover absolute -end-0.5 -bottom-0.5 flex size-7 cursor-pointer items-center justify-center rounded-full text-white shadow-sm"
        >
          <Icon icon={uploading ? 'loader-2' : 'camera'} className={cn('size-4', uploading && 'animate-spin')} />
        </button>
        <input ref={fileInput} type="file" accept="image/*" className="hidden" onChange={pick} />
      </div>
      {person.avatar && (
        <button type="button" onClick={remove} className="text-default-400 hover:text-danger cursor-pointer text-xs">
          Remove photo
        </button>
      )}
      {error && <p className="text-danger max-w-40 text-center text-xs">{error}</p>}
    </div>
  )
}

// --- Identity card (left column) ----------------------------------------------

const IdentityCard = ({ person, roles }: { person: PersonInfo; roles: string[] }) => (
  <div className="card">
    <div className="card-body">
      <div className="mb-7.5 flex items-center">
        <div className="gap-base flex items-center">
          <Avatar person={person} />
          <div>
            <h5 className="font-medium">{person.name}</h5>
            <p className="text-default-400 mb-3">{roles[0] ?? 'Member'}</p>
            <div className="flex flex-wrap gap-1.5">
              <span
                className={cn(
                  'badge badge-label',
                  person.is_active ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary',
                )}
              >
                {person.status}
              </span>
              {roles.slice(1).map((role) => (
                <span key={role} className="badge badge-label bg-primary/15 text-primary">
                  {role}
                </span>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div className="flex flex-col gap-y-3">
        <FactRow icon="mail" label="Email">
          {person.email}
        </FactRow>
        <FactRow icon="phone" label="Phone">
          {person.phone ?? 'Not set'}
        </FactRow>
        {person.hire_date && (
          <FactRow icon="calendar" label="Hired">
            {person.hire_date}
          </FactRow>
        )}
        {person.joined && (
          <FactRow icon="calendar-plus" label="Member since">
            {person.joined}
          </FactRow>
        )}
      </div>

      <p className="text-default-400 border-default-200 mt-6 border-t pt-4 text-xs">
        Phone, address, and other personal details are managed by HR — submit an info change request to update
        them.
      </p>
    </div>
  </div>
)

/**
 * Settings live at /admin/settings on the back office and /settings on QC
 * Minute, served by the same controllers — so every form here posts relative
 * to the surface it was rendered on.
 */
const useSettingsBase = (): string => {
  const { surface } = usePage().props as { surface?: string }

  return surface === 'qcminute' ? '/settings' : '/admin/settings'
}

// --- Profile tab ---------------------------------------------------------------

/** The card footer every settings form ends with: the submit button and a "saved" note. */
const FormFooter = ({ label, processing, saved, savedLabel = 'Saved' }: { label: string; processing: boolean; saved: boolean; savedLabel?: string }) => (
  <div className="border-default-300 flex items-center gap-3 border-t px-6 py-4">
    <button type="submit" className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white" disabled={processing}>
      {label}
    </button>
    {saved && (
      <span className="text-success flex items-center gap-1 text-sm">
        <Icon icon="circle-check" className="size-4" /> {savedLabel}
      </span>
    )}
  </div>
)

const CardHeading = ({ title, children }: { title: string; children?: React.ReactNode }) => (
  <div className="card-header">
    <div>
      <h4 className="card-title">{title}</h4>
      {children && <p className="text-default-400 mt-1 text-sm">{children}</p>}
    </div>
  </div>
)

const ProfileTab = ({ person, roles }: { person: PersonInfo; roles: string[] }) => {
  const base = useSettingsBase()
  const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
    name: person.name,
    email: person.email,
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch(`${base}/profile`, { preserveScroll: true })
  }

  return (
    <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
      <IdentityCard person={person} roles={roles} />

      <form onSubmit={submit} className="card mb-0 xl:col-span-2">
        <CardHeading title="Your details">Your name and the email address you sign in with.</CardHeading>
        <div className="card-body grid gap-5 md:grid-cols-2">
          <div>
            <label htmlFor="name" className="form-label">
              Name
            </label>
            <input id="name" className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            {errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}
          </div>
          <div>
            <label htmlFor="email" className="form-label">
              Email
            </label>
            <input id="email" type="email" className="form-input" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
            <p className="text-default-400 mt-1 text-xs">You use this address to sign in.</p>
            {errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}
          </div>
        </div>
        <FormFooter label="Save Changes" processing={processing} saved={recentlySuccessful} />
      </form>
    </div>
  )
}

// --- Security tab ----------------------------------------------------------------

const SecurityTab = ({ email }: { email: string }) => {
  const base = useSettingsBase()
  const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    put(`${base}/password`, {
      preserveScroll: true,
      onSuccess: () => reset(),
      onError: () => reset('current_password'),
    })
  }

  return (
    <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
      <form onSubmit={submit} className="card mb-0 xl:col-span-2">
        <CardHeading title="Change password">Enter your current password, then the new one twice.</CardHeading>
        <div className="card-body grid gap-5 md:grid-cols-2">
          <div className="md:col-span-2 md:max-w-[calc(50%-0.625rem)]">
            <label htmlFor="current_password" className="form-label">
              Current Password
            </label>
            <input
              id="current_password"
              type="password"
              autoComplete="current-password"
              className="form-input"
              value={data.current_password}
              onChange={(e) => setData('current_password', e.target.value)}
              required
            />
            {errors.current_password && <p className="text-danger mt-1 text-sm">{errors.current_password}</p>}
          </div>
          <div>
            <label htmlFor="password" className="form-label">
              New Password
            </label>
            <input
              id="password"
              type="password"
              autoComplete="new-password"
              className="form-input"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              required
            />
            <p className="text-default-400 mt-1 text-xs">At least 8 characters.</p>
            {errors.password && <p className="text-danger mt-1 text-sm">{errors.password}</p>}
          </div>
          <div>
            <label htmlFor="password_confirmation" className="form-label">
              Confirm New Password
            </label>
            <input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              className="form-input"
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
              required
            />
          </div>
        </div>
        <FormFooter label="Update Password" processing={processing} saved={recentlySuccessful} savedLabel="Password changed" />
      </form>

      <div className="card mb-0 h-fit">
        <CardHeading title="Signing in" />
        <div className="card-body space-y-3 text-sm">
          <p className="text-default-600">
            You sign in as <span className="text-default-900 font-semibold">{email}</span>.
          </p>
          <p className="text-default-500">Forgot your password? Sign out and use “Forgot password” on the sign-in page. We’ll email you a link to choose a new one.</p>
        </div>
      </div>
    </div>
  )
}

// --- Notifications tab -----------------------------------------------------------

const NotificationsTab = ({ settings }: { settings: NotificationSettings }) => {
  const base = useSettingsBase()
  const { data, setData, patch, processing, recentlySuccessful } = useForm({
    muted: settings.muted,
  })

  const toggle = (value: string, receive: boolean) =>
    setData('muted', receive ? data.muted.filter((v) => v !== value) : [...data.muted, value])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch(`${base}/notifications`, { preserveScroll: true })
  }

  return (
    <form onSubmit={submit} className="card mb-0 max-w-3xl">
      <CardHeading title="Notification preferences">
        Switching one off silences every way it reaches you, including email where a category sends it. Anything that needs your action still shows
        up in My Tasks and on your dashboard.
      </CardHeading>

      <div className="divide-default-100 divide-y px-6">
        {settings.categories.map((category) => {
          const receive = !data.muted.includes(category.value)
          return (
            <label key={category.value} className="flex cursor-pointer items-start justify-between gap-4 py-4">
              <span>
                <span className="text-dark block font-medium">
                  {category.label}
                  {category.emails && <span className="badge badge-label bg-info/15 text-info ms-2">Email too</span>}
                </span>
                <span className="text-default-400 text-sm">{category.description}</span>
              </span>
              <input
                type="checkbox"
                className="form-switch mt-1 shrink-0"
                checked={receive}
                onChange={(e) => toggle(category.value, e.target.checked)}
              />
            </label>
          )
        })}
      </div>

      <FormFooter label="Save Preferences" processing={processing} saved={recentlySuccessful} />
    </form>
  )
}

// --- My access tab --------------------------------------------------------------

const AccessTab = ({ access, onOpenNotifications }: { access: Access; onOpenNotifications: () => void }) => {
  const [showCant, setShowCant] = useState(false)
  const always = access.notices.filter((n) => !n.only_if_theirs)
  const ifTheirs = access.notices.filter((n) => n.only_if_theirs)

  const summary = [
    {
      label: access.roles.length === 1 ? 'Your role' : 'Your roles',
      value: access.roles.join(', ') || 'None yet',
      note: 'Set by an Admin. Ask them if it looks wrong.',
    },
    {
      label: 'Properties you look after',
      value: access.all_properties ? 'All properties' : access.properties.length > 0 ? access.properties.join(', ') : 'None yet',
      note: access.all_properties ? 'Your role sees every property.' : 'You see contractors, hours and invoices for these only.',
    },
    {
      label: 'You can do',
      value: `${access.can_count} of ${access.total} things`,
      note: access.is_super_admin ? 'Super Admin can do everything.' : 'Listed below by area.',
    },
  ]

  return (
    <div className="space-y-5">
      <div className="grid gap-5 md:grid-cols-3">
        {summary.map((s) => (
          <div key={s.label} className="card mb-0">
            <div className="card-body">
              <div className="text-default-400 text-sm">{s.label}</div>
              <div className="text-default-900 text-lg font-semibold tabular-nums">{s.value}</div>
              <div className="text-default-500 text-sm">{s.note}</div>
            </div>
          </div>
        ))}
      </div>

      <div className="grid items-start gap-5 xl:grid-cols-5">
        <section className="card mb-0 xl:col-span-3">
          <div className="card-header flex flex-wrap items-center justify-between gap-3">
            <h4 className="card-title">What you can do</h4>
            <label className="text-default-500 flex cursor-pointer items-center gap-2 text-sm">
              <input type="checkbox" className="form-checkbox" checked={showCant} onChange={(e) => setShowCant(e.target.checked)} />
              Show what you can’t do too
            </label>
          </div>
          <div className="card-body divide-default-100 divide-y py-2">
            {access.areas.length === 0 && <p className="text-default-400 py-3 text-sm">Nothing yet. An Admin assigns what each role can do.</p>}
            {access.areas.map((a) => (
              <div key={a.name} className="py-3.5">
                <div className="mb-2 flex items-baseline justify-between gap-3">
                  <span className="text-default-800 font-semibold">{a.name}</span>
                  <span className="text-default-400 text-xs tabular-nums">
                    {a.can.length} of {a.can.length + a.cant.length}
                  </span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {a.can.map((label) => (
                    <span key={label} className="badge badge-label bg-success/15 text-success">
                      <Icon icon="check" className="size-3.5" />
                      {label}
                    </span>
                  ))}
                  {showCant &&
                    a.cant.map((label) => (
                      <span key={label} className="badge badge-label border-default-300 text-default-400 border border-dashed line-through">
                        {label}
                      </span>
                    ))}
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="card mb-0 xl:col-span-2">
          <div className="card-header">
            <div>
              <h4 className="card-title">What you’ll be notified about</h4>
              <button type="button" onClick={onOpenNotifications} className="text-primary mt-1 text-sm font-medium hover:underline">
                Change these on the Notifications tab
              </button>
            </div>
          </div>
          <div className="card-body py-2">
            {!access.has_email && <p className="text-default-400 py-3 text-sm">There’s no email address on your profile, so these arrive in the app only.</p>}
            {always.length === 0 && <p className="text-default-400 py-3 text-sm">Nothing is sent to your role automatically.</p>}
            <div className="divide-default-100 divide-y">
              {always.map((n) => (
                <div key={n.name} className="flex items-start gap-3 py-3">
                  <div className="min-w-0 flex-1">
                    <div className="text-default-800 font-medium">{n.name}</div>
                    <div className="text-default-400 text-xs">{n.summary}</div>
                  </div>
                  {n.muted ? (
                    <span className="badge badge-label bg-default-100 text-default-500">Muted</span>
                  ) : (
                    <span className={cn('badge badge-label', n.email ? 'bg-secondary/15 text-secondary' : 'bg-primary/15 text-primary')}>
                      {n.in_app && n.email ? 'In-app + email' : n.email ? 'Email' : 'In-app'}
                    </span>
                  )}
                </div>
              ))}
            </div>
            {ifTheirs.length > 0 && (
              <div className="bg-default-50 my-3 rounded-lg px-4 py-3 text-sm">
                <span className="text-default-600">Also, only for your own requests and account: </span>
                <span className="text-default-800">{ifTheirs.map((n) => n.name).join(', ')}.</span>
              </div>
            )}
          </div>
        </section>
      </div>

      <div className="border-default-300 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed px-5 py-4">
        <span className="text-default-600">Need access to something that isn’t listed? Ask an Admin.</span>
        {access.reference_url && (
          <Link href={access.reference_url} className="text-primary text-sm font-medium hover:underline">
            See every role’s access →
          </Link>
        )}
      </div>
    </div>
  )
}

// --- Page ----------------------------------------------------------------------

const TABS: { key: string; label: string; isNew?: boolean }[] = [
  { key: 'profile', label: 'Profile' },
  { key: 'security', label: 'Security' },
  { key: 'notifications', label: 'Notifications' },
  { key: 'access', label: 'My access', isNew: true },
]

const Page = () => {
  const { person, roles, notificationSettings, access } = usePage().props as unknown as Props

  // Active tab lives in the URL hash (#profile / #security) so tabs are
  // deep-linkable and survive refresh. Invalid/missing hash → first tab.
  const tabFromHash = () => {
    const hash = typeof window === 'undefined' ? '' : window.location.hash.slice(1)
    return TABS.some((t) => t.key === hash) ? hash : TABS[0].key
  }
  const [active, setActive] = useState(tabFromHash)

  useEffect(() => {
    const onHashChange = () => setActive(tabFromHash())
    window.addEventListener('hashchange', onHashChange)
    return () => window.removeEventListener('hashchange', onHashChange)
  }, [])

  const selectTab = (key: string) => {
    setActive(key)
    window.history.replaceState(null, '', `#${key}`)
  }

  return (
    <>
      <Head title="My Profile" />
      <PageBreadcrumb title="My Profile" subtitle="Account" />

      {/* Tabs sit under the page title, full width, as on the System Reference pages. */}
      <nav className="border-default-300 mb-5 flex flex-wrap gap-1 border-b" aria-label="Profile sections" role="tablist">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            role="tab"
            aria-selected={active === t.key}
            onClick={() => selectTab(t.key)}
            className={cn(
              '-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 font-medium focus:outline-hidden',
              active === t.key ? 'border-primary text-default-900' : 'text-default-500 hover:text-default-900 border-transparent',
            )}
          >
            {t.label}
            {t.isNew && <span className="badge badge-label bg-secondary/15 text-secondary">New</span>}
          </button>
        ))}
      </nav>

      {active === 'profile' && <ProfileTab person={person} roles={roles} />}
      {active === 'security' && <SecurityTab email={person.email} />}
      {active === 'notifications' && <NotificationsTab settings={notificationSettings} />}
      {active === 'access' && <AccessTab access={access} onOpenNotifications={() => selectTab('notifications')} />}
    </>
  )
}

export default Page
