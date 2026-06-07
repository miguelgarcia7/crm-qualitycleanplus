import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Image Menu" subtitle="Layouts" />
      <LayoutSwitcher attribute="sidenavColor" value="image" />
      <LayoutInfo option="sidenavColor" value="image" />
    </>
  )
}

export default Page
