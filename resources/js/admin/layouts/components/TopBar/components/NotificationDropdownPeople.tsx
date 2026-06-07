import user4 from '@/images/admin/users/user-4.jpg'
import user5 from '@/images/admin/users/user-5.jpg'
import user6 from '@/images/admin/users/user-6.jpg'
import user7 from '@/images/admin/users/user-7.jpg'
import user8 from '@/images/admin/users/user-8.jpg'
import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'
import SimpleBar from 'simplebar-react'

const NotificationDropdownPeople = () => {
  return (
    <div id="notification-dropdown-people" className="topbar-item hs-dropdown relative inline-flex [--auto-close:inside] [--placement:bottom-right]">
      <button className="topbar-link hs-dropdown-toggle relative flex items-center" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
        <Icon icon="bell" className="topbar-link-icon" />
        <span className="badge bg-danger absolute -end-px -top-4 size-4 rounded-full leading-0 text-white">5</span>
      </button>

      <div className="hs-dropdown-menu min-w-80 p-0 space-y-0" role="menu" aria-orientation="vertical" aria-labelledby="dropdown-menu">
        <div className="border-default-300 border-b px-3 py-2">
          <div className="flex items-center justify-between">
            <h6 className="text-base font-semibold">Notifications</h6>
            <Link href="#!" className="badge badge-label py-1.5 bg-success/15 text-success">
              07 Notification
            </Link>
          </div>
        </div>

        <SimpleBar style={{ maxHeight: '300px' }}>
          <div className="dropdown-item gap-6 px-4.5 py-3 text-wrap" id="message-1">
            <span className="shrink-0 relative">
              <img src={user4} className="size-9 rounded-full" alt="User Avatar" />
              <span className="absolute -top-3 -end-2 border-2 border-card bg-success text-white flex size-5.5 items-center justify-center rounded-full">
                <Icon icon="bell" className="text-2xs align-middle" />
                <span className="sr-only">unread notification</span>
              </span>
            </span>

            <span className="grow text-default-400">
              <span className="font-medium text-body-color">Emily Johnson</span>
              commented on a task in
              <span className="font-medium text-body-color">Design Sprint</span>
              <br />
              <span className="text-xs">12 minutes ago</span>
            </span>
          </div>

          <div className="dropdown-item gap-6 px-4.5 py-3 text-wrap" id="message-2">
            <span className="shrink-0 relative">
              <img src={user5} className="size-9 rounded-full" alt="User Avatar" />
              <span className="absolute -top-3 -end-2 border-2 border-card bg-info text-white flex size-5.5 items-center justify-center rounded-full">
                <Icon icon="cloud-upload" className="text-2xs align-middle" />
                <span className="sr-only">upload notification</span>
              </span>
            </span>
            <span className="grow text-default-400">
              <span className="font-medium text-body-color">Michael Lee</span>
              uploaded files to
              <span className="font-medium text-body-color">Marketing Assets</span>
              <br />
              <span className="text-xs">25 minutes ago</span>
            </span>
          </div>

          <div className="dropdown-item gap-6 px-4.5 py-3 text-wrap" id="message-6">
            <span className="shrink-0 relative">
              <span className="size-9 rounded-full bg-light flex items-center justify-center">
                <Icon icon="database" className="text-lg" />
              </span>
              <span className="absolute -top-3 -end-2 border-2 border-card bg-danger text-white flex size-5.5 items-center justify-center rounded-full">
                <Icon icon="alert-circle" className="text-2xs align-middle" />
                <span className="sr-only">server alert</span>
              </span>
            </span>
            <span className="grow text-default-400">
              <span className="font-medium text-body-color">Server #3</span>
              CPU usage exceeded 90%
              <br />
              <span className="text-xs">Just now</span>
            </span>
          </div>

          <div className="dropdown-item gap-6 px-4.5 py-3 text-wrap" id="message-3">
            <span className="shrink-0 relative">
              <img src={user6} className="size-9 rounded-full" alt="User Avatar" />
              <span className="absolute -top-3 -end-2 border-2 border-card bg-warning text-white flex size-5.5 items-center justify-center rounded-full">
                <Icon icon="alert-triangle" className="text-2xs align-middle" />
                <span className="sr-only">alert</span>
              </span>
            </span>
            <span className="grow text-default-400">
              <span className="font-medium text-body-color">Sophia Ray</span>
              flagged an issue in
              <span className="font-medium text-body-color">Bug Tracker</span>
              <br />
              <span className="text-xs">40 minutes ago</span>
            </span>
          </div>

          <div className="dropdown-item gap-6 px-4.5 py-3 text-wrap" id="message-4">
            <span className="shrink-0 relative">
              <img src={user7} className="size-9 rounded-full" alt="User Avatar" />
              <span className="absolute -top-3 -end-2 border-2 border-card bg-primary text-white flex size-5.5 items-center justify-center rounded-full">
                <Icon icon="calendar-event" className="text-2xs align-middle" />
                <span className="sr-only">event notification</span>
              </span>
            </span>
            <span className="grow text-default-400">
              <span className="font-medium text-body-color">David Kim</span>
              scheduled a meeting for
              <span className="font-medium text-body-color">UX Review</span>
              <br />
              <span className="text-xs">1 hour ago</span>
            </span>
          </div>

          <div className="dropdown-item gap-6 px-4.5 py-3 text-wrap" id="message-5">
            <span className="shrink-0 relative">
              <img src={user8} className="size-9 rounded-full" alt="User Avatar" />
              <span className="absolute -top-3 -end-2 border-2 border-card bg-secondary text-white flex size-5.5 items-center justify-center rounded-full">
                <Icon icon="edit" className="text-2xs align-middle" />
                <span className="sr-only">edit</span>
              </span>
            </span>
            <span className="grow text-default-400">
              <span className="font-medium text-body-color">Isabella White</span>
              updated the document in
              <span className="font-medium text-body-color">Product Specs</span>
              <br />
              <span className="text-xs">2 hours ago</span>
            </span>
          </div>

          <div className="dropdown-item gap-6 px-4.5 py-3 text-wrap" id="message-7">
            <span className="shrink-0 relative">
              <span className="size-9 rounded-full bg-light flex items-center justify-center">
                <Icon icon="rocket" className="text-lg" />
              </span>
              <span className="absolute -top-3 -end-2 border-2 border-card bg-success text-white flex size-5.5 items-center justify-center rounded-full">
                <Icon icon="check" className="text-2xs align-middle" />
                <span className="sr-only">deployment</span>
              </span>
            </span>
            <span className="grow text-default-400">
              <span className="font-medium text-body-color">Production Server</span>
              deployment completed successfully
              <br />
              <span className="text-xs">30 minutes ago</span>
            </span>
          </div>
        </SimpleBar>

        <Link href="" className="dropdown-item text-reset border-light justify-center border-t py-3 font-bold underline underline-offset-2">
          Read All Messages
        </Link>
      </div>
    </div>
  )
}

export default NotificationDropdownPeople
