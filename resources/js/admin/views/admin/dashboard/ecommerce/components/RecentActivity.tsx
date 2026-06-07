import Icon from '@/components/wrappers/Icon'
import { SimpleBar } from '@/components/wrappers/SimpleBar'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'
import { activityData } from './data'

const RecentActivity = () => {
  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">Recent Activity</h4>
        <div className="hs-dropdown [--placement:bottom-right] ms-auto">
          <Link href="" className="hs-dropdown-toggle btn btn-sm btn-icon border-default-300 hover:text-primary">
            <Icon icon="dots-vertical" className="text-base" />
          </Link>
          <ul className="hs-dropdown-menu">
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="activity" />
                View Activity Log
              </Link>
            </li>
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="filter-2" />
                Filter Activities
              </Link>
            </li>
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="download" />
                Export Logs
              </Link>
            </li>
            <li>
              <hr className="dropdown-divider" />
            </li>
            <li>
              <Link className="dropdown-item text-danger" href="">
                <Icon icon="trash" /> Clear Activity
              </Link>
            </li>
          </ul>
        </div>
      </div>
      <SimpleBar className="card-body" style={{ maxHeight: 426 }}>
        <div>
          {activityData.map((item, idx) => (
            <div className="flex gap-x-5" key={idx}>
              <div
                className={cn('relative after:absolute after:top-7 after:bottom-0 after:start-3.5 after:w-0 after:border-s after:border-dashed after:border-default-300 after:-translate-x-[0.5px]', {
                  'after:content-none': idx === activityData.length - 1,
                })}
              >
                <div className="relative z-10">
                  <div className={cn('flex justify-center items-center rounded-full size-7.5', item.className)}>
                    <Icon icon={item.icon} className="text-base text-white" />
                  </div>
                </div>
              </div>
              <div
                className={cn('grow', {
                  'pb-7.5': idx !== activityData.length - 1,
                })}
              >
                <h5 className="mb-1.25">{item.title}</h5>
                <p className="mb-1.25 text-default-400">{item.description}</p>
                <span className="text-primary">By {item.author}</span>
              </div>
            </div>
          ))}
        </div>
      </SimpleBar>
    </div>
  )
}

export default RecentActivity
