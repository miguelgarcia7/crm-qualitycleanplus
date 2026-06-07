import { useLayoutContext } from '@/context/useLayoutContext'
import { toTitleCase } from '@/utils/helpers'
import type { CustomizationOptionType } from '../index'

import darkImg from '@/images/admin/layouts/topbar-color-dark.png'
import gradientImg from '@/images/admin/layouts/topbar-color-gradient.png'
import grayImg from '@/images/admin/layouts/topbar-color-gray.png'
import lightImg from '@/images/admin/layouts/topbar-color-light.png'

const topbarColorOptions: CustomizationOptionType[] = [
  { value: 'light', image: lightImg },
  { value: 'dark', image: darkImg },
  { value: 'gray', image: grayImg },
  { value: 'gradient', image: gradientImg },
]

const TopBarColor = () => {
  const { updateSettings, topbarColor } = useLayoutContext()

  const handleTopBarColorChange = (value: string) => {
    updateSettings({ topbarColor: value })
  }

  return (
    <div className="p-5">
      <h5 className="text-md mb-base font-bold">Topbar Color</h5>
      <div className="gap-base grid grid-cols-3">
        {topbarColorOptions &&
          topbarColorOptions.map((option, idx) => (
            <div className="card-radio" key={idx}>
              <input className="hidden" type="radio" name="data-topbar-color" id={`layout-topbar-color-${option.value}`} checked={topbarColor === option.value} onChange={() => handleTopBarColorChange(option.value)} />
              <label className="form-label" htmlFor={`layout-topbar-color-${option.value}`}>
                <img src={option.image} alt="layout img" className="flex size-full rounded-md" />
              </label>
              <h5 className="text-md text-default-600 mt-2.5 text-center">{toTitleCase(option.value)}</h5>
            </div>
          ))}
      </div>
    </div>
  )
}

export default TopBarColor
