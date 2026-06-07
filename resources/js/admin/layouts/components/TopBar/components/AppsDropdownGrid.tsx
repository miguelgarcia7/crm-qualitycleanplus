import dropboxLogo from '@/images/admin/logos/dropbox.svg'
import figmaLogo from '@/images/admin/logos/figma.svg'
import googleLogo from '@/images/admin/logos/google.svg'
import slackLogo from '@/images/admin/logos/slack.svg'
import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

const AppsDropdown = () => {
  return (
    <div id="apps-dropdown-grid" className="xl:inline-flex hidden">
      <div className="topbar-item hs-dropdown relative inline-flex [--auto-close:inside] [--placement:bottom-right]">
        <button className="topbar-link hs-dropdown-toggle relative flex items-center" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <Icon icon="apps" className="text-xl topbar-link-icon" />
        </button>
        <div className="hs-dropdown-menu w-80 p-3" role="menu" aria-orientation="vertical" aria-labelledby="dropdown-menu">
          <div className="grid grid-cols-3 items-center gap-1.5">
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full bg-light flex items-center justify-center mx-auto mb-1.25">
                <img src={googleLogo} alt="Google Logo" className="h-4.5" />
              </span>
              <span className="align-middle fw-medium">Google</span>
            </Link>
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full bg-light flex items-center justify-center mx-auto mb-1.25">
                <img src={figmaLogo} alt="Figma Logo" className="h-4.5" />
              </span>
              <span className="align-middle fw-medium">Figma</span>
            </Link>
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full bg-light flex items-center justify-center mx-auto mb-1.25">
                <img src={slackLogo} alt="Slack Logo" className="h-4.5" />
              </span>
              <span className="align-middle fw-medium">Slack</span>
            </Link>
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full bg-light flex items-center justify-center mx-auto mb-1.25">
                <img src={dropboxLogo} alt="Dropbox Logo" className="h-4.5" />
              </span>
              <span className="align-middle fw-medium">Dropbox</span>
            </Link>
            <div className="text-center">
              <Link href="" className="btn btn-sm btn-icon rounded-full bg-danger text-white hover:bg-danger-hover">
                <Icon icon="circle-dashed-plus" className="text-lg" />
              </Link>
            </div>
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full flex items-center justify-center bg-primary/15 text-primary mx-auto mb-1.25">
                <Icon icon="calendar" className="text-lg" />
              </span>
              <span className="align-middle fw-medium">Calendar</span>
            </Link>
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full flex items-center justify-center bg-primary/15 text-primary mx-auto mb-1.25">
                <Icon icon="message-circle" className="text-lg" />
              </span>
              <span className="align-middle fw-medium">Chat</span>
            </Link>
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full flex items-center justify-center bg-primary/15 text-primary mx-auto mb-1.25">
                <Icon icon="folder" className="text-lg" />
              </span>
              <span className="align-middle fw-medium">Files</span>
            </Link>
            <Link href="" className="dropdown-item flex-col gap-0 border border-dashed border-default-300 rounded text-center py-3">
              <span className="size-8 rounded-full flex items-center justify-center bg-primary/15 text-primary mx-auto mb-1.25">
                <Icon icon="users" className="text-lg" />
              </span>
              <span className="align-middle fw-medium">Team</span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}

export default AppsDropdown
