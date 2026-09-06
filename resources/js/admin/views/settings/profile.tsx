import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router, useForm, usePage } from '@inertiajs/react'
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

type Props = {
  person: PersonInfo
  roles: string[]
  notificationSettings: NotificationSettings
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
  const fileInput = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const pick = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setError(null)
    router.post(
      '/admin/settings/avatar',
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
      onConfirm: () => router.delete('/admin/settings/avatar', { preserveScroll: true }),
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

// --- Profile tab ---------------------------------------------------------------

const ProfileTab = ({ person }: { person: PersonInfo }) => {
  const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
    name: person.name,
    email: person.email,
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch('/admin/settings/profile', { preserveScroll: true })
  }

  return (
    <form onSubmit={submit} className="max-w-lg">
      <div className="mb-4">
        <label htmlFor="name" className="form-label">
          Name
        </label>
        <input
          id="name"
          className="form-input"
          value={data.name}
          onChange={(e) => setData('name', e.target.value)}
          required
        />
        {errors.name && <p className="text-danger mt-1 text-sm">{errors.name}</p>}
      </div>
      <div className="mb-5">
        <label htmlFor="email" className="form-label">
          Email
        </label>
        <input
          id="email"
          type="email"
          className="form-input"
          value={data.email}
          onChange={(e) => setData('email', e.target.value)}
          required
        />
        <p className="text-default-400 mt-1 text-xs">You use this address to sign in.</p>
        {errors.email && <p className="text-danger mt-1 text-sm">{errors.email}</p>}
      </div>
      <div className="flex items-center gap-3">
        <button
          type="submit"
          className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white"
          disabled={processing}
        >
          Save Changes
        </button>
        {recentlySuccessful && (
          <span className="text-success flex items-center gap-1 text-sm">
            <Icon icon="circle-check" className="size-4" /> Saved
          </span>
        )}
      </div>
    </form>
  )
}

// --- Security tab ----------------------------------------------------------------

const SecurityTab = () => {
  const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    put('/admin/settings/password', {
      preserveScroll: true,
      onSuccess: () => reset(),
      onError: () => reset('current_password'),
    })
  }

  return (
    <form onSubmit={submit} className="max-w-lg">
      <div className="mb-4">
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
      <div className="mb-4">
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
      <div className="mb-5">
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
      <div className="flex items-center gap-3">
        <button
          type="submit"
          className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white"
          disabled={processing}
        >
          Update Password
        </button>
        {recentlySuccessful && (
          <span className="text-success flex items-center gap-1 text-sm">
            <Icon icon="circle-check" className="size-4" /> Password changed
          </span>
        )}
      </div>
    </form>
  )
}

// --- Notifications tab -----------------------------------------------------------

const NotificationsTab = ({ settings }: { settings: NotificationSettings }) => {
  const { data, setData, patch, processing, recentlySuccessful } = useForm({
    muted: settings.muted,
  })

  const toggle = (value: string, receive: boolean) =>
    setData('muted', receive ? data.muted.filter((v) => v !== value) : [...data.muted, value])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch('/admin/settings/notifications', { preserveScroll: true })
  }

  return (
    <form onSubmit={submit} className="max-w-lg">
      <p className="text-default-400 mb-5 text-sm">
        Choose which notifications you receive. Switching one off silences every way it reaches you —
        including email, where a category sends it. Anything that needs your action still shows up in My
        Tasks and on your dashboard.
      </p>

      <div className="space-y-5">
        {settings.categories.map((category) => {
          const receive = !data.muted.includes(category.value)
          return (
            <label key={category.value} className="flex cursor-pointer items-start justify-between gap-4">
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

      <div className="mt-6 flex items-center gap-3">
        <button
          type="submit"
          className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white"
          disabled={processing}
        >
          Save Preferences
        </button>
        {recentlySuccessful && (
          <span className="text-success flex items-center gap-1 text-sm">
            <Icon icon="circle-check" className="size-4" /> Saved
          </span>
        )}
      </div>
    </form>
  )
}

// --- Page ----------------------------------------------------------------------

const TABS = [
  { key: 'profile', label: 'Profile' },
  { key: 'security', label: 'Security' },
  { key: 'notifications', label: 'Notifications' },
]

const Page = () => {
  const { person, roles, notificationSettings } = usePage().props as unknown as Props

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

      <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
        <div className="space-y-6">
          <IdentityCard person={person} roles={roles} />
        </div>

        <div className="space-y-6 xl:col-span-2">
          <div className="card">
            <nav className="border-default-300 flex flex-wrap border-b px-4 pt-2" aria-label="Tabs" role="tablist">
              {TABS.map((t) => (
                <button
                  key={t.key}
                  type="button"
                  role="tab"
                  aria-selected={active === t.key}
                  onClick={() => selectTab(t.key)}
                  className={cn(
                    'hover:text-primary -mb-px inline-flex items-center px-4 py-2 text-center font-medium focus:outline-hidden',
                    active === t.key ? 'border-primary text-primary border-b' : '',
                  )}
                >
                  {t.label}
                </button>
              ))}
            </nav>

            <div className="card-body p-6">
              {active === 'profile' && <ProfileTab person={person} />}
              {active === 'security' && <SecurityTab />}
              {active === 'notifications' && <NotificationsTab settings={notificationSettings} />}
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
