import bgPattern from '@/images/admin/user-bg-pattern.svg'
import user1 from '@/images/admin/users/user-1.jpg'
import Icon from '@/components/wrappers/Icon'
import { META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'

const UserProfileSettings = () => {
  return (
    <div id="user-profile-settings" className={`sidenav-user p-5`} style={{ backgroundImage: `url("${bgPattern}")` }}>
      <div className="flex items-center justify-between">
        <div>
          <Link href="" className="link-reset">
            <img src={user1} alt="user-image" className="mb-3 size-9 rounded-full" />
            <span className="sidenav-user-name block font-bold text-nowrap">{META_DATA.username}</span>
            <span className="text-xs font-semibold" data-lang="user-role">
              Art Director
            </span>
          </Link>
        </div>
        <div>
          <div className="hs-dropdown relative inline-flex [--placement:bottom-right]">
            <button className="cursor-pointer" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
              <Icon icon="settings" className="ms-1 size-6 align-middle" />
            </button>
            <div className="hs-dropdown-menu" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-with-icons">
              <div className="py-2 px-3.5">
                <h6 className="text-xs">Welcome back 👋!</h6>
              </div>
              <Link href="" className="dropdown-item">
                <Icon icon="user-circle" className="me-1 align-middle text-lg" />
                <span className="align-middle">Profile</span>
              </Link>
              <Link href="" className="dropdown-item">
                <Icon icon="settings-2" className="me-1 align-middle text-lg" />
                <span className="align-middle">Account Settings</span>
              </Link>
              <Link href="/auth/lock-screen" className="dropdown-item">
                <Icon icon="lock" className="me-1 align-middle text-lg" />
                <span className="align-middle">Lock Screen</span>
              </Link>
              <Link href="" className="dropdown-item text-danger">
                <Icon icon="logout" className="me-1 align-middle text-lg" />
                <span className="align-middle">Log Out</span>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default UserProfileSettings
