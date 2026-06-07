import { useLayoutContext } from '@/context/useLayoutContext'
import { toTitleCase } from '@/utils/helpers'
import type { CustomizationOptionType } from '../index'

import darkImg from '@/images/admin/layouts/sidenav-color-dark.png'
import gradientImg from '@/images/admin/layouts/sidenav-color-gradient.png'
import grayImg from '@/images/admin/layouts/sidenav-color-gray.png'
import imageImg from '@/images/admin/layouts/sidenav-color-image.png'
import lightImg from '@/images/admin/layouts/sidenav-color-light.png'

const sidenavColorOptions: CustomizationOptionType[] = [
  { value: 'light', image: lightImg },
  { value: 'dark', image: darkImg },
  { value: 'gray', image: grayImg },
  { value: 'gradient', image: gradientImg },
  { value: 'image', image: imageImg },
]

const SidenavColor = () => {
  const { updateSettings, sidenavColor } = useLayoutContext()

  const handleSidenavColorChange = (value: string) => {
    updateSettings({ sidenavColor: value })
  }

  return (
    <div className="p-5">
      <h5 className="text-md mb-base font-bold">Sidenav Color</h5>
      <div className="gap-base grid grid-cols-3">
        {sidenavColorOptions &&
          sidenavColorOptions.map((option, idx) => (
            <div className="card-radio" key={idx}>
              <input className="hidden" type="radio" name="data-menu-color" id={`layout-sidenav-color-${option.value}`} checked={sidenavColor === option.value} onChange={() => handleSidenavColorChange(option.value)} />
              <label className="form-label" htmlFor={`layout-sidenav-color-${option.value}`}>
                <img src={option.image} alt="layout img" className="flex size-full rounded-md" />
              </label>
              <h5 className="text-md text-default-600 mt-2.5 text-center">{toTitleCase(option.value)}</h5>
            </div>
          ))}
      </div>
    </div>
  )
}

export default SidenavColor
