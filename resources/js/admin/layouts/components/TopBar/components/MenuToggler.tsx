import Icon from '@/components/wrappers/Icon'
import { showBackdrop, useLayoutContext } from '@/context/useLayoutContext'

const MenuToggler = () => {
  const { updateSettings, sidenavSize } = useLayoutContext()

  const toggleSideNav = () => {
    const currentSize = sidenavSize

    if (currentSize === 'offcanvas') {
      showBackdrop()
    } else if (sidenavSize === 'default') {
      updateSettings({ sidenavSize: currentSize === 'default' ? 'condensed' : 'default' })
    } else {
      updateSettings({ sidenavSize: currentSize === 'condensed' ? 'default' : 'condensed' })
    }
  }

  return (
    <button id="button-toggle-menu" className="sidenav-toggle-button btn rounded-full bg-primary btn-icon text-white" onClick={toggleSideNav}>
      <Icon icon="menu-4" className="text-xl" />
    </button>
  )
}

export default MenuToggler
