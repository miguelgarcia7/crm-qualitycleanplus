import settingsBg from '@/images/admin/settings-bg.png'
import Icon from '@/components/wrappers/Icon'
import { SimpleBar } from '@/components/wrappers/SimpleBar'
import { useLayoutContext } from '@/context/useLayoutContext'
import Dir from './components/Dir'
import Orientation from './components/Orientation'
import Position from './components/Position'
import SidenavColor from './components/SidenavColor'
import SidenavSize from './components/SidenavSize'
import SidenavUser from './components/SidenavUser'
import Skin from './components/Skin'
import Theme from './components/Theme'
import TopBarColor from './components/TopbarColor'
import Width from './components/Width'

export type CustomizationOptionType = {
  value: string
  image: string
}

const Customizer = () => {
  const { reset } = useLayoutContext()

  return (
    <div>
      <div
        id="theme-customization"
        className="hs-overlay hs-overlay-open:translate-x-0 bg-card hs-overlay-open:flex fixed inset-y-0 end-0 bottom-0 z-80 hidden w-full max-w-[400px] translate-x-full transform flex-col overflow-hidden transition-all duration-300 rtl:-translate-x-full"
      >
        <div className="bg-primary text-default-600 border-default-900/10 flex items-start gap-3 border-b border-dashed p-6" style={{ backgroundImage: `url("${settingsBg}")` }}>
          <div>
            <h5 className="mb-1.25 text-sm font-bold text-white uppercase">Admin Customizer</h5>
            <p className="font-medium text-white/75 italic">Easily configure layout, styles, and preferences for your admin interface.</p>
          </div>
          <div className="grow">
            <button  type="button" data-hs-overlay="#theme-customization" className="btn btn-sm bg-default-100/20 size-7.5 rounded-full text-white">
              <Icon icon="x" className="text-base" />
            </button>
          </div>
        </div>
        <SimpleBar className="h-full grow overflow-y-auto">
          <div className="divide-default-300 divide-y divide-dashed">
            <Skin />

            <Dir />

            <SidenavSize />

            <Theme />

            <SidenavColor />

            <TopBarColor />

            <Width />

            <Orientation />

            <Position />

            <SidenavUser />
          </div>
        </SimpleBar>
        <div className="border-default-900/10 flex border-t p-5">
          <div className="grid w-full grid-cols-2 gap-4">
            <a href="https://www.studiomex.com" rel="noopener noreferrer" className="btn py-3 w-full bg-success hover:bg-success-hover text-white">
              <Icon icon="basket" /> Buy Now
            </a>
            <button type="button" className="btn py-3 w-full bg-danger text-white hover:bg-danger-hover" onClick={reset}>
              <Icon icon="refresh" className="text-base" /> Reset
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Customizer
