import PageBreadcrumb from '@/components/PageBreadcrumb'
import LayoutInfo from '../LayoutInfo'
import LayoutSwitcher from '../LayoutSwitcher'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Compact Menu" subtitle="Layouts" />
      <LayoutSwitcher attribute="sidenavSize" value="md" />
      <LayoutInfo option="sidenavSize" value="compact" />
    </>
  )
}

export default Page
