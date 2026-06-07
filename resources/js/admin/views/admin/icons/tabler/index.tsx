import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Icon as IconifyIcon } from '@iconify/react'


export type TablerIconType = {
  iconName: string
  name: string
}

export const tablerIconData: TablerIconType[] = [
  { iconName: 'phone', name: 'Phone' },
  { iconName: 'ad-2', name: 'Ad 2' },
  { iconName: 'headphones', name: 'Headphones' },
  { iconName: 'camera', name: 'Camera' },
  { iconName: 'device-watch', name: 'Watch' },
  { iconName: 'microphone', name: 'Microphone' },
  { iconName: 'headset', name: 'Headset' },
  { iconName: 'device-tablet', name: 'Tablet' },
  { iconName: 'device-gamepad', name: 'Gamepad' },
  { iconName: 'printer', name: 'Printer' },
  { iconName: 'device-speaker', name: 'Speaker' },
  { iconName: 'database', name: 'Database' },
  { iconName: 'cloud', name: 'Cloud' },
  { iconName: 'wifi', name: 'Wi-Fi' },
  { iconName: 'bluetooth', name: 'Bluetooth' },
  { iconName: 'usb', name: 'USB' },
  { iconName: 'folder', name: 'Folder' },
  { iconName: 'lock', name: 'Lock' },
  { iconName: 'key', name: 'Key' },
  { iconName: 'shield', name: 'Shield' },
  { iconName: 'paperclip', name: 'Paperclip' },
  { iconName: 'bell', name: 'Bell' },
  { iconName: 'search', name: 'Search' },
  { iconName: 'briefcase', name: 'Briefcase' },
  { iconName: 'shopping-cart', name: 'Cart' },
  { iconName: 'file', name: 'File' },
  { iconName: 'book', name: 'Book' },
  { iconName: 'mail', name: 'Mail' },
  { iconName: 'user', name: 'User' },
  { iconName: 'user-circle', name: 'User Circle' },
  { iconName: 'phone-call', name: 'Phone Call' },
  { iconName: 'music', name: 'Music' },
  { iconName: 'movie', name: 'Movie' },
  { iconName: 'file-upload', name: 'Upload' },
  { iconName: 'cloud-upload', name: 'Cloud Upload' },
  { iconName: 'share', name: 'Share' },
  { iconName: 'arrow-right', name: 'Arrow Right' },
  { iconName: 'arrow-left', name: 'Arrow Left' },
  { iconName: 'arrow-up', name: 'Arrow Up' },
  { iconName: 'arrow-down', name: 'Arrow Down' },
  { iconName: 'search', name: 'Search' },
  { iconName: 'refresh', name: 'Refresh' },
]

const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Tabler" subtitle="Icons" />

      <div className="container-fluid">
        <div className="card">
          <div className="card-header block">
            <h4 className="card-title mb-1.25">Overview</h4>
            <p className="text-default-400">Free and open source icons designed to make your website or app attractive, visually consistent and simply beautiful.</p>
          </div>

          <div className="card-body">
            <h4 className="mb-2 text-sm">Usage</h4>

            <div className="mt-3 flex items-center gap-3">
              <IconifyIcon icon="tabler:phone" className="text-2xl" />
              <IconifyIcon icon="tabler:ad-2" className="text-2xl" />
              <IconifyIcon icon="tabler:device-desktop" className="text-2xl" />
              <IconifyIcon icon="tabler:device-tablet" className="text-2xl" />
              <IconifyIcon icon="tabler:device-gamepad" className="text-2xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-2xl" />
            </div>
          </div>

          <div className="card-body border-default-300 border-t border-dashed">
            <h4 className="mb-2 text-sm">Colors</h4>

            <div className="mt-3 flex items-center gap-3">
              <IconifyIcon icon="tabler:camera" className="text-primary text-2xl" />
              <IconifyIcon icon="tabler:chart-pie-2" className="text-secondary text-2xl" />
              <IconifyIcon icon="tabler:bell" className="text-success text-2xl" />
              <IconifyIcon icon="tabler:credit-card" className="text-info text-2xl" />
              <IconifyIcon icon="tabler:cloud" className="text-warning text-2xl" />
              <IconifyIcon icon="tabler:mail" className="text-danger text-2xl" />
              <IconifyIcon icon="tabler:lock" className="text-dark text-2xl" />
              <IconifyIcon icon="tabler:user" className="text-purple text-2xl" />
              <IconifyIcon icon="tabler:star" className="text-light text-2xl" />
            </div>
          </div>
          <div className="card-body border-default-300 border-t border-dashed">
            <h4 className="mb-2 text-sm">Sizes</h4>

            <div className="mt-3 flex items-center gap-3">
              <IconifyIcon icon="tabler:phone" className="text-xs" />
              <IconifyIcon icon="tabler:ad-2" className="text-sm" />
              <IconifyIcon icon="tabler:device-desktop" className="text-base" />
              <IconifyIcon icon="tabler:device-tablet" className="text-lg" />
              <IconifyIcon icon="tabler:device-watch" className="text-xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-2xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-3xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-4xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-5xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-6xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-7xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-8xl" />
              <IconifyIcon icon="tabler:device-watch" className="text-9xl" />
            </div>
            <div className="mt-3 flex items-center gap-3">
              <IconifyIcon icon="tabler:device-watch" />
              <IconifyIcon icon="tabler:device-watch" className="size-3.25" />
              <IconifyIcon icon="tabler:device-watch" className="size-3.75" />
              <IconifyIcon icon="tabler:device-watch" className="text-base" />
              <IconifyIcon icon="tabler:device-watch" className="text-lg" />
              <IconifyIcon icon="tabler:device-watch" className="size-6" />
              <IconifyIcon icon="tabler:device-watch" className="size-8" />
              <IconifyIcon icon="tabler:device-watch" className="size-9" />
              <IconifyIcon icon="tabler:device-watch" className="size-10.5" />
              <IconifyIcon icon="tabler:device-watch" className="size-15" />
            </div>
          </div>

          <div className="card-body border-default-300 border-t border-dashed">
            <h4 className="mt-0 mb-3">Icons</h4>
            <div className="align-items-center flex flex-wrap gap-3 text-center">
              {tablerIconData.map((item, idx) => (
                <div className="border-default-300 flex size-20 flex-col items-center justify-center gap-3 truncate rounded border border-dashed" key={idx}>
                  <IconifyIcon icon={`tabler:${item.iconName}`} className="text-2xl" />
                  <span className="block w-full truncate text-center font-semibold">{item.name}</span>
                </div>
              ))}
            </div>

            <div className="mt-3 text-center">
              <a href="https://tabler.io/icons" target="_blank" className="btn bg-danger text-white hover:bg-danger-hover">
                View All Icons
              </a>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
