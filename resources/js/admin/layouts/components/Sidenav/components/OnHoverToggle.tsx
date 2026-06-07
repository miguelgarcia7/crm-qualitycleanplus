import { useLayoutContext } from '@/context/useLayoutContext'

const OnHoverToggle = () => {
  const { sidenavSize, updateSettings } = useLayoutContext()

  const handleToggle = () => {
    updateSettings({ sidenavSize: sidenavSize === 'on-hover-active' ? 'on-hover' : 'on-hover-active' })
  }

  return (
    <div className="h-topbar justify absolute end-5 top-0 flex items-center">
      <button id="button-hover-toggle" onClick={handleToggle}>
        <span className="btn-on-hover-icon"></span>
      </button>
    </div>
  )
}

export default OnHoverToggle
