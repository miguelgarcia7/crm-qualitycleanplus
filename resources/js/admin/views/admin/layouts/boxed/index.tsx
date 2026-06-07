import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Boxed" subtitle="Layouts" />
      <LayoutSwitcher attribute="width" value="boxed" />
      <LayoutInfo option="width" value="boxed" />
    </>
  )
}

export default Page
