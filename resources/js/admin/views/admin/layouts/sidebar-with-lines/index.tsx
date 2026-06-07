import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import HTMLClassHandler from '../HTMLClassHandler'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="Sidebar With Lines" subtitle="Layouts" />
      <HTMLClassHandler classes={['sidebar-with-line']} />

      <div className="container-fluid">
        <div className="bg-info/10 text-info border-s-3 border-info rounded-md flex items-start gap-3 py-3 px-4 mb-4">
          <div>
            <Icon icon="info-circle" className="text-xl" />
          </div>
          <div>
            If you want to display a line with each menu item, add the class
            <code>&quot;sidebar-with-line&quot;</code>
            to the
            <code>&lt;html&gt;</code>
            tag.
          </div>
        </div>

        <div className="card">
          <div className="card-body text-center">
            <h4 className="text-lg">Your custom content here</h4>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
