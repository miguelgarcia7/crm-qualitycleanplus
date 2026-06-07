import Icon from '@/components/wrappers/Icon'
import { SimpleBar } from '@/components/wrappers/SimpleBar'
import { Link } from '@inertiajs/react'

const Megamenu = () => {
  return (
    <div id="megamenu-columns" className="md:inline-flex hidden">
      <div className="topbar-item hs-dropdown relative inline-flex">
        <button className="topbar-link hs-dropdown-toggle btn px-2.5! font-medium" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          Mega Menu <Icon icon="chevron-down" />
        </button>
        <div className="hs-dropdown-menu p-0 md:min-w-3xl" role="menu" aria-orientation="vertical" aria-labelledby="dropdown-menu">
          <SimpleBar style={{ maxHeight: 380 }} data-simplebar>
            <div className="grid md:grid-cols-3">
              <div className="p-3">
                <h5 className="py-2 px-3.5 font-semibold mb-2 text-sm">Dashboard &amp; Analytics</h5>
                <ul className="list-unstyled megamenu-list">
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      Sales Dashboard
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      Marketing Dashboard
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      Finance Overview
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      User Analytics
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      Traffic Insights
                    </Link>
                  </li>
                </ul>
              </div>
              <div className="p-3">
                <h5 className="py-2 px-3.5 font-semibold mb-2 text-sm">Project Management</h5>
                <ul className="list-unstyled megamenu-list">
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="minus" className="align-middle me-1 text-default-400" />
                      Kanban Workflow
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="minus" className="align-middle me-1 text-default-400" />
                      Project Timeline
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="minus" className="align-middle me-1 text-default-400" />
                      Task Management
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="minus" className="align-middle me-1 text-default-400" />
                      Team Members
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="minus" className="align-middle me-1 text-default-400" />
                      Assignments
                    </Link>
                  </li>
                </ul>
              </div>
              <div className="p-3 bg-light/50">
                <h5 className="py-2 px-3.5 font-semibold mb-2 text-sm">User Management</h5>
                <ul className="list-unstyled megamenu-list">
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      User Profiles
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      Access Control
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      Security Settings
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      User Groups
                    </Link>
                  </li>
                  <li>
                    <Link href="" className="dropdown-item">
                      <Icon icon="chevron-right" className="align-middle me-1 text-default-400" />
                      Authentication
                    </Link>
                  </li>
                </ul>
              </div>
            </div>
            {/* end row*/}
          </SimpleBar>
        </div>
      </div>
    </div>
  )
}

export default Megamenu
