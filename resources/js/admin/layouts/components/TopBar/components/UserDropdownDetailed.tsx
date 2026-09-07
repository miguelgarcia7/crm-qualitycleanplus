import Icon from '@/components/wrappers/Icon'
import { Link, usePage } from '@inertiajs/react'

type Auth = {
  user?: { name?: string; email?: string }
  avatar?: string | null
  roles?: string[]
}

const prettyRole = (role?: string) =>
  role ? role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : 'Member'

const initialsOf = (name: string) =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

const UserDropdown = () => {
  const { auth = {}, surface } = usePage().props as { auth?: Auth; surface?: string }
  const name = auth.user?.name ?? 'Account'
  const role = prettyRole(auth.roles?.[0])
  const onMinute = surface === 'qcminute'

  return (
    <div className="topbar-item hs-dropdown before:bg-default-700/35 relative inline-flex before:h-4.5 before:w-px before:content-['']">
      <button className="hs-dropdown-toggle topbar-link ms-2.5 cursor-pointer items-center px-3! flex" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
        {auth.avatar ? (
          <img src={auth.avatar} alt={name} className="size-8 rounded-full object-cover lg:me-3" />
        ) : (
          <span className="bg-primary/15 text-primary flex size-8 items-center justify-center rounded-full text-xs font-semibold lg:me-3">
            {initialsOf(name)}
          </span>
        )}
        <div className="hidden lg:flex items-center gap-1.5">
          <span className="flex flex-col items-start">
            <h5 className="pro-username">{name}</h5>
            <span className="text-xs/none mb-0.5">{role}</span>
          </span>
          <Icon icon="chevron-down" className="align-middle" />
        </div>
      </button>
      <div className="hs-dropdown-menu min-w-48" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-with-icons">
        <div className="py-2 px-3.5">
          <h6 className="text-xs">Welcome back 👋!</h6>
          {auth.user?.email && <p className="text-default-400 truncate text-xs">{auth.user.email}</p>}
        </div>

        <Link href={onMinute ? '/settings/profile' : '/admin/settings/profile'} className="dropdown-item">
          <Icon icon="user-circle" className="me-1 fs-lg align-middle" />
          <span className="align-middle">Profile</span>
        </Link>

        {/* Self-service info changes go through an approval workflow, so they
            stay separate from the settings a person edits directly. */}
        {onMinute && (
          <Link href="/my-info" className="dropdown-item">
            <Icon icon="id-badge-2" className="me-1 fs-lg align-middle" />
            <span className="align-middle">My Info</span>
          </Link>
        )}

        <div className="dropdown-divider"></div>

        <Link href="/logout" method="post" as="button" className="dropdown-item w-full text-start fw-semibold">
          <Icon icon="logout" className="me-1 fs-lg align-middle" />
          <span className="align-middle">Log Out</span>
        </Link>
      </div>
    </div>
  )
}

export default UserDropdown
