import { useLayoutContext } from '@/context/useLayoutContext'
import { toTitleCase } from '@/utils/helpers'
import type { CustomizationOptionType } from '../index'

import darkImg from '@/images/admin/layouts/theme-dark.png'
import lightImg from '@/images/admin/layouts/theme-light.png'
import systemImg from '@/images/admin/layouts/theme-system.png'

const themeOptions: CustomizationOptionType[] = [
  { value: 'light', image: lightImg },
  { value: 'dark', image: darkImg },
  { value: 'system', image: systemImg },
]

const Theme = () => {
  const { updateSettings, theme } = useLayoutContext()

  const handleThemeChange = (value: string) => {
    updateSettings({ theme: value })
  }

  return (
    <div className="p-5">
      <h5 className="text-md mb-5 font-bold">Theme Mode</h5>
      <div className="grid lg:grid-cols-3 md:grid-cols-2 gap-base">
        {themeOptions.map((item, idx) => (
          <div className="card-radio" key={idx}>
            <input className="hidden" type="radio" name="data-theme" id={`layout-color-${item.value}`} checked={theme === item.value} onChange={() => handleThemeChange(item.value)} />
            <label className="form-label" htmlFor={`layout-color-${item.value}`}>
              <img src={item.image} alt="layout img" className="flex size-full rounded-md" />
            </label>
            <h5 className="text-md text-default-600 mt-2.5 text-center">{toTitleCase(item.value)}</h5>
          </div>
        ))}
      </div>
    </div>
  )
}

export default Theme
