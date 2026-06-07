import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Offcanvas Menu" subtitle="Layouts" />
      <LayoutSwitcher attribute="sidenavSize" value="offcanvas" />
      <LayoutInfo option="sidenavSize" value="offcanvas" />
    </>
  )
}

export default Page
