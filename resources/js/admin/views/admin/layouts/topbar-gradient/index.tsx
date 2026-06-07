import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Gradient Topbar" subtitle="Layouts" />
      <LayoutSwitcher attribute="topbarColor" value="gradient" />
      <LayoutInfo option="topbarColor" value="gradient" />
    </>
  )
}

export default Page
