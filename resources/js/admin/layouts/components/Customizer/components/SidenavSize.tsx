import { useLayoutContext } from '@/context/useLayoutContext'
import { toTitleCase } from '@/utils/helpers'
import type { CustomizationOptionType } from '../index'

import compactImg from '@/images/admin/layouts/sidenav-size-compact.png'
import condensedImg from '@/images/admin/layouts/sidenav-size-condensed.png'
import defaultImg from '@/images/admin/layouts/sidenav-size-default.png'
import offcanvasImg from '@/images/admin/layouts/sidenav-size-offcanvas.png'
import onHoverActiveImg from '@/images/admin/layouts/sidenav-size-on-hover-active.png'
import onHoverImg from '@/images/admin/layouts/sidenav-size-on-hover.png'

const sidenavSizeOptions: CustomizationOptionType[] = [
  { value: 'default', image: defaultImg },
  { value: 'compact', image: compactImg },
  { value: 'condensed', image: condensedImg },
  { value: 'on-hover', image: onHoverImg },
  { value: 'on-hover-active', image: onHoverActiveImg },
  { value: 'offcanvas', image: offcanvasImg },
]

const SidenavSize = () => {
  const { updateSettings, sidenavSize } = useLayoutContext()

  const handleSidenavSizeChange = (value: string) => {
    updateSettings({ sidenavSize: value })
  }

  return (
    <div className="p-5">
      <h5 className="text-md mb-5 font-bold">Sidenav View</h5>
      <div className="grid lg:grid-cols-3 md:grid-cols-2 gap-base">
        {sidenavSizeOptions &&
          sidenavSizeOptions.map((option, idx) => (
            <div className="card-radio" key={idx}>
              <input className="hidden" type="radio" name="data-sidenav-size" id={`layout-sidenav-size-${option.value}`} checked={sidenavSize === option.value} onChange={() => handleSidenavSizeChange(option.value)} />
              <label className="form-label" htmlFor={`layout-sidenav-size-${option.value}`}>
                <img src={option.image} alt="layout img" className="flex size-full rounded-md" />
              </label>
              <h5 className="text-md text-default-600 mt-2.5 text-center">{toTitleCase(option.value)}</h5>
            </div>
          ))}
      </div>
    </div>
  )
}

export default SidenavSize
