import { useLayoutContext } from '@/context/useLayoutContext'

const Position = () => {
  const { updateSettings, position } = useLayoutContext()

  const handlePositionChange = (value: string) => {
    updateSettings({ position: value })
  }

  return (
    <div className="p-6" id="sidenav-user">
      <div className="flex items-center justify-between">
        <h5 className="font-bold">Layout Position</h5>
        <div className="flex gap-1">
          <div id="position-fixed">
            <input type="radio" className="peer hidden" name="data-layout-position" id="layout-position-fixed" checked={position === 'fixed'} onChange={() => handlePositionChange('fixed')} />
            <label className="btn btn-sm bg-warning/15 text-warning peer-checked:bg-warning peer-checked:text-white" htmlFor="layout-position-fixed">
              Fixed
            </label>
          </div>
          <div id="position-scrollable">
            <input type="radio" className="peer hidden" name="data-layout-position" id="layout-position-scrollable" checked={position === 'scrollable'} onChange={() => handlePositionChange('scrollable')} />
            <label className="btn btn-sm bg-warning/15 text-warning peer-checked:bg-warning peer-checked:text-white" htmlFor="layout-position-scrollable">
              Scrollable
            </label>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Position
