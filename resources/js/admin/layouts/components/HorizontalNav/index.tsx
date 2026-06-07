import AppMenu from './components/AppMenu'

const HorizontalNav = () => {
  return (
    <div className="topnav top-topbar-height border-default-300 fixed z-30 flex w-full items-center border-b bg-(--sidenav-bg)">
      <div className="container-fluid">
        <nav id="topnav-menu" className="hs-collapse hidden grow basis-full overflow-hidden transition-all duration-300 lg:block" aria-labelledby="topnav-menu-collapse">
          <AppMenu />
        </nav>
      </div>
    </div>
  )
}

export default HorizontalNav
