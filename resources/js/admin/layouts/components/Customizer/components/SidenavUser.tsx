import { useLayoutContext } from '@/context/useLayoutContext'

const SidenavUser = () => {
  const { updateSettings, sidenavUser } = useLayoutContext()

  const handleSidenavUserChange = (value: boolean) => {
    updateSettings({ sidenavUser: value })
  }

  return (
    <div className="p-6" id="sidenav-user">
      <div className="flex items-center justify-between">
        <label className="m-0 font-bold" htmlFor="sidebaruser-check">
          Sidebar User Info
        </label>
        <input type="checkbox" className="form-switch" name="sidebar-user" id="sidebaruser-check" checked={sidenavUser} onChange={(e) => handleSidenavUserChange(e.target.checked)} />
      </div>
    </div>
  )
}

export default SidenavUser
