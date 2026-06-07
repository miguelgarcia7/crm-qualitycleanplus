import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Gradient Menu" subtitle="Layouts" />
      <LayoutSwitcher attribute="sidenavColor" value="gradient" />
      <LayoutInfo option="sidenavColor" value="gradient" />
    </>
  )
}

export default Page
