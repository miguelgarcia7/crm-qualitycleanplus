import { useLayoutContext } from '@/context/useLayoutContext'
import { toPascalCase } from '@/utils/helpers'
import type { CustomizationOptionType } from '../index'

import boxedImg from '@/images/admin/layouts/width-boxed.png'
import fluidImg from '@/images/admin/layouts/width-fluid.png'

const widthOptions: CustomizationOptionType[] = [
  { value: 'fluid', image: fluidImg },
  { value: 'boxed', image: boxedImg },
]

const Width = () => {
  const { updateSettings, width } = useLayoutContext()

  const handleWidthChange = (value: string) => {
    updateSettings({ width: value })
  }

  return (
    <div id="width" className="p-5">
      <h5 className="text-md mb-5 font-bold">Layout Width</h5>
      <div className="grid lg:grid-cols-3 md:grid-cols-2 gap-base">
        {widthOptions.map((item, idx) => (
          <div className="card-radio" id={`width-${item.value}`} key={idx}>
            <input className="hidden" type="radio" name="data-layout-width" id={`layout-width-${item.value}`} defaultValue="fluid" checked={width === item.value} onChange={() => handleWidthChange(item.value)} />
            <label className="form-label" htmlFor={`layout-width-${item.value}`}>
              <img src={item.image} alt="layout img" className="flex size-full rounded-md" />
            </label>
            <h5 className="text-md text-default-600 mt-2.5 text-center">{toPascalCase(item.value)}</h5>
          </div>
        ))}
      </div>
    </div>
  )
}

export default Width
