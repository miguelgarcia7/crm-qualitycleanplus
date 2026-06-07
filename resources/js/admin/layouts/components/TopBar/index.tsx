import useScrollEvent from '@/hooks/useScrollEvent'
import clsx from 'clsx'
import AppsDropdownGrid from './components/AppsDropdownGrid'
import CustomizerToggler from './components/CustomizerToggler'
import FullscreenToggler from './components/FullscreenToggler'
import MenuToggler from './components/MenuToggler'

import LanguageSelectorRounded from './components/LanguageSelectorRounded'

import MegamenuApps from './components/MegamenuApps'
import MegamenuColumns from './components/MegamenuColumns'

import MonochromeToggler from './components/MonochromeToggler'

import NotificationDropdownPeople from './components/NotificationDropdownPeople'

import SearchBoxRounded from './components/SearchBoxRounded'

import ThemeDropdown from './components/ThemeDropdown'

import UserDropdownDetailed from './components/UserDropdownDetailed'

const TopBar = () => {
  const { scrollY } = useScrollEvent()

  return (
    <header className={clsx('app-header', { 'topbar-active': scrollY > 50 })}>
      <div className="container-fluid flex items-center justify-between">
        <div className="flex items-center gap-2.5">
          <MenuToggler />

          <SearchBoxRounded />

          <MegamenuColumns />

          <MegamenuApps />
        </div>
        <div className="flex items-center gap-3">
          <LanguageSelectorRounded />

          <AppsDropdownGrid />

          <NotificationDropdownPeople />

          <ThemeDropdown />

          <FullscreenToggler />

          <CustomizerToggler />

          <MonochromeToggler />

          <UserDropdownDetailed />
        </div>
      </div>
    </header>
  )
}

export default TopBar
