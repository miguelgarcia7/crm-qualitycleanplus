import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import HTMLClassHandler from '../HTMLClassHandler'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="No Icons" subtitle="Layouts" />
      <HTMLClassHandler classes={['sidebar-no-icons', 'sidebar-with-line']} />

      <div className="container-fluid">
        <div className="bg-info/10 text-info border-s-3 border-info rounded-md flex items-start gap-3 py-3 px-4 mb-4">
          <div>
            <Icon icon="info-circle" className="text-xl" />
          </div>
          <div>
            If you want to remove icons and display sidebar items in line style, add the class
            <code>&apos;sidebar-no-icons sidebar-with-line&apos;</code> to the
            <code>&lt;html&gt;</code> tag.
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
