import stockSmall from '@/images/admin/stock/small-8.jpg'
import Icon from '@/components/wrappers/Icon'
import { SimpleBar } from '@/components/wrappers/SimpleBar'
import { cn, splitArray } from '@/utils/helpers'
import { Link } from '@inertiajs/react'

const Megamenu = () => {
  return (
    <div id="megamenu-apps" className="md:inline-flex hidden">
      <div className="topbar-item hs-dropdown relative inline-flex">
        <button className="topbar-link hs-dropdown-toggle btn px-2.5! font-medium" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          Apps <Icon icon="chevron-down" />
        </button>
        <div className="hs-dropdown-menu p-0 md:min-w-3xl" role="menu" aria-orientation="vertical" aria-labelledby="dropdown-menu">
          <SimpleBar style={{ maxHeight: 380 }}>
            <div className="grid md:grid-cols-3">
              {chunkedApps.map((chunk, idx) => (
                <div className="p-3 space-y-2" key={idx}>
                  {chunk.map((app, idx) => (
                    <Link href={app.href} className="dropdown-item" key={idx}>
                      <span className="flex items-center gap-3">
                        <span className={cn('size-9 flex items-center justify-center text-primary border border-light bg-light/50 rounded', app.iconClassName)}>
                          <Icon icon={app.icon} className="size-5.5" />
                        </span>
                        <span>
                          <h5 className="text-xs">{app.title}</h5>
                          <span className="text-default-400 text-xs">{app.subtitle}</span>
                        </span>
                      </span>
                    </Link>
                  ))}
                </div>
              ))}

              <div className="row-span-2 bg-light/50">
                <div className="h-full relative rounded-e overflow-hidden bg-cover" style={{ backgroundImage: `url("${stockSmall}")` }}>
                  <div className="p-6 absolute inset-0 bg-gradient bg-secondary/90 flex items-center justify-center">
                    <div className="text-center text-white">
                      <Icon icon="atom" className="text-4xl mx-auto" />
                      <p className="text-white/75 mb-5 uppercase">Limited Offer</p>
                      <h3 className="font-semibold text-white mb-3 text-xl">Unlock Exclusive Savings</h3>
                      <h4 className="font-medium text-base mb-1">
                        <del className="text-opacity-75 text-white">$49.00</del>/<span className="font-bold text-white">$15 USD</span>
                      </h4>
                      <button type="button" className="mt-5 btn btn-sm bg-danger text-white hover:bg-danger-hover">
                        <Icon icon="shopping-cart" className="me-1.5" />
                        Buy Now
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div className="col-span-2">
                <div className="grid grid-cols-2 border-t border-light border-dashed text-center">
                  <div className="p-6">
                    <p className="font-medium text-default-400 mb-1 text-2xs uppercase">-: &nbsp; Support &nbsp;:-</p>
                    <h5 className="text-md">help@mydomain.com</h5>
                  </div>
                  <div className="p-6">
                    <p className="font-medium text-default-400 mb-1 text-2xs uppercase">-: &nbsp; Help: &nbsp;:-</p>
                    <h5 className="text-md">+(12) 3456 7890</h5>
                  </div>
                </div>
              </div>
            </div>
          </SimpleBar>
        </div>
      </div>
    </div>
  )
}

export default Megamenu

type AppMenuItemType = {
  icon: string
  iconClassName: string
  title: string
  subtitle: string
  href: string
}

const appsMenuData: AppMenuItemType[] = [
  {
    icon: 'basket',
    iconClassName: 'text-primary',
    title: 'eCommerce',
    subtitle: 'Products, orders & etc.',
    href: '',
  },
  {
    icon: 'message',
    iconClassName: 'text-success',
    title: 'Chat',
    subtitle: 'Team conversations',
    href: '',
  },
  {
    icon: 'list-check',
    iconClassName: 'text-danger',
    title: 'Task',
    subtitle: 'Plan and track work',
    href: '',
  },
  {
    icon: 'mailbox',
    iconClassName: 'text-info',
    title: 'Email',
    subtitle: 'Messages and inbox',
    href: '',
  },
  {
    icon: 'building',
    iconClassName: 'text-secondary',
    title: 'Companies',
    subtitle: 'Business profiles',
    href: '',
  },
  {
    icon: 'id',
    iconClassName: 'text-dark',
    title: 'Contacts Diary',
    subtitle: 'People and connections',
    href: '',
  },
  {
    icon: 'calendar',
    iconClassName: 'text-warning',
    title: 'Calendar',
    subtitle: 'Events and reminders',
    href: '',
  },
  {
    icon: 'lifebuoy',
    iconClassName: 'text-success',
    title: 'Support',
    subtitle: 'Help and assistance',
    href: '',
  },
]

const chunkedApps = splitArray(appsMenuData, 4)
