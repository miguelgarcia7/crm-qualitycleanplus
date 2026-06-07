import { useLayoutContext } from '@/context/useLayoutContext'
import { toTitleCase } from '@/utils/helpers'
import type { CustomizationOptionType } from '../index'

import verticalImg from '@/images/admin/layouts/sidenav-color-dark.png'
import horizontalImg from '@/images/admin/layouts/sidenav-size-offcanvas.png'

const orientationOptions: CustomizationOptionType[] = [
  { value: 'vertical', image: verticalImg },
  { value: 'horizontal', image: horizontalImg },
]

const Orientation = () => {
  const { updateSettings, orientation } = useLayoutContext()

  const handleOrientationChange = (value: string) => {
    updateSettings({ orientation: value })
  }

  return (
    <div className="p-5">
      <h5 className="text-md mb-base font-bold">Orientation</h5>
      <div className="gap-base grid grid-cols-3">
        {orientationOptions.map((item, idx) => (
          <div className="card-radio" key={idx}>
            <input className="hidden" type="radio" name="data-orientation" id={`layout-orientation-${item.value}`} checked={orientation === item.value} onChange={() => handleOrientationChange(item.value)} />
            <label className="form-label" htmlFor={`layout-orientation-${item.value}`}>
              <img src={item.image} alt="layout img" className="flex size-full rounded-md" />
            </label>
            <h5 className="text-md text-default-600 mt-2.5 text-center">{toTitleCase(item.value)}</h5>
          </div>
        ))}
      </div>
    </div>
  )
}

export default Orientation
