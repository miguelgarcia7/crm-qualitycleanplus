import { useLayoutContext } from '@/context/useLayoutContext'
import type { CustomizationOptionType } from '../index'

import ltrImg from '@/images/admin/layouts/dir-ltr.png'
import rtlImg from '@/images/admin/layouts/dir-rtl.png'
import { cn } from '@/utils/helpers'

const directionOptions: CustomizationOptionType[] = [
  { value: 'ltr', image: ltrImg },
  { value: 'rtl', image: rtlImg },
]

const Dir = () => {
  const { updateSettings, dir } = useLayoutContext()

  const handleDirectionChange = (value: string) => {
    updateSettings({ dir: value })
  }

  return (
    <div id="direction" className="p-5">
      <h5 className="text-md mb-5 font-bold">Theme Direction</h5>

      <div className="grid lg:grid-cols-3 md:grid-cols-2 gap-base">
        {directionOptions.map((option) => (
          <div className="card-radio" key={option.value} id={`direction-${option.value}`}>
            <input className="hidden" type="radio" name="data-layout-direction" id={`layout-direction-${option.value}`} checked={dir === option.value} onChange={() => handleDirectionChange(option.value)} />

            <label className="form-label" htmlFor={`layout-direction-${option.value}`}>
              <img src={option.image} alt="direction img" className={cn('flex size-full rounded-md', option.value === 'rtl' ? 'scale-x-[-1]' : '')} />
            </label>

            <h5 className="text-md text-default-600 mt-2.5 text-center">{option.value.toUpperCase()} Mode</h5>
          </div>
        ))}
      </div>
    </div>
  )
}

export default Dir
