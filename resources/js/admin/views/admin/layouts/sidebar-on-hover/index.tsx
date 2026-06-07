import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="On Hover Menu" subtitle="Layouts" />
      <LayoutSwitcher attribute="sidenavSize" value="on-hover" />
      <LayoutInfo option="sidenavSize" value="on-hover" />
    </>
  )
}

export default Page
