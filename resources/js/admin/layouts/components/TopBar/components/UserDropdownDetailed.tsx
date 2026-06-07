import User1 from '@/images/admin/users/user-1.jpg'
import Icon from '@/components/wrappers/Icon'
import { META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'
import { Fragment } from 'react'

type UserProfileMenuType = {
  label: string
  icon: string
  link: string
  divider?: boolean
  className?: string
}

const userProfileMenuData: UserProfileMenuType[] = [
  {
    label: 'Profile',
    icon: 'user-circle',
    link: '',
  },
  {
    label: 'Notifications',
    icon: 'bell-ringing',
    link: '',
  },
  {
    label: 'Account Settings',
    icon: 'settings-2',
    link: '',
  },
  {
    label: 'Support Center',
    icon: 'headset',
    link: '',
    divider: true,
  },
  {
    label: 'Lock Screen',
    icon: 'lock',
    link: '/auth/lock-screen',
  },
  {
    label: 'Log Out',
    icon: 'logout',
    link: '',
    className: 'fw-semibold',
  },
]
const UserDropdown = () => {
  return (
    <div className="topbar-item hs-dropdown before:bg-default-700/35 relative inline-flex before:h-4.5 before:w-px before:content-['']">
      <button className="hs-dropdown-toggle topbar-link ms-2.5 cursor-pointer items-center px-3! flex" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
        <img src={User1} alt="user-image" className="size-8 rounded-full lg:me-3" />
        <div className="hidden lg:flex items-center gap-1.5">
          <span className="flex flex-col items-start">
            <h5 className="pro-username">{META_DATA.username}</h5>
            <span className="text-xs/none mb-0.5">Admin Head</span>
          </span>
          <Icon icon="chevron-down" className="align-middle" />
        </div>
      </button>
      <div className="hs-dropdown-menu min-w-48" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-with-icons">
        <div className="py-2 px-3.5">
          <h6 className="text-xs">Welcome back 👋!</h6>
        </div>
        {userProfileMenuData.map((item, idx) => (
          <Fragment key={idx}>
            <Link href={item.link} className="dropdown-item">
              <Icon icon={item.icon} className="me-1 fs-lg align-middle" />
              <span className="align-middle">{item.label}</span>
            </Link>
            {item.divider && <div className="dropdown-divider"></div>}
          </Fragment>
        ))}
      </div>
    </div>
  )
}

export default UserDropdown
