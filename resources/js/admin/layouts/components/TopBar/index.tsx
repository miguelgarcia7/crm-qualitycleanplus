import useScrollEvent from '@/hooks/useScrollEvent'
import clsx from 'clsx'
import FullscreenToggler from './components/FullscreenToggler'
import MenuToggler from './components/MenuToggler'
import MonochromeToggler from './components/MonochromeToggler'
import NotificationDropdownPeople from './components/NotificationDropdownPeople'
import ThemeDropdown from './components/ThemeDropdown'
import UserDropdownDetailed from './components/UserDropdownDetailed'

const TopBar = () => {
  const { scrollY } = useScrollEvent()

  return (
    <header className={clsx('app-header', { 'topbar-active': scrollY > 50 })}>
      <div className="container-fluid flex items-center justify-between">
        <div className="flex items-center gap-2.5">
          <MenuToggler />
        </div>
        <div className="flex items-center gap-3">
          <NotificationDropdownPeople />

          <ThemeDropdown />

          <FullscreenToggler />

          <MonochromeToggler />

          <UserDropdownDetailed />
        </div>
      </div>
    </header>
  )
}

export default TopBar
