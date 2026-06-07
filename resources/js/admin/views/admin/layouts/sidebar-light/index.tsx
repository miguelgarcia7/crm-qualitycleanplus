import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Light Menu" subtitle="Layouts" />
      <LayoutSwitcher attribute="sidenavColor" value="light" />
      <LayoutInfo option="sidenavColor" value="light" />
    </>
  )
}

export default Page
