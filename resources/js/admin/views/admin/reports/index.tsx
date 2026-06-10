import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head, Link } from '@inertiajs/react'

type ReportCard = {
  href: string
  icon: string
  title: string
  description: string
}

type Props = {
  groups: { group: string; reports: ReportCard[] }[]
}

const Page = ({ groups }: Props) => (
  <>
    <Head title="Reports" />
    <PageBreadcrumb title="Reports" subtitle="Catalog" />

    <div className="space-y-6">
      {groups.map((group) => (
        <div key={group.group}>
          <h4 className="text-default-500 mb-3 text-sm font-semibold uppercase">{group.group}</h4>
          <div className="gap-base grid sm:grid-cols-2 xl:grid-cols-3">
            {group.reports.map((report) => (
              <Link key={report.href} href={report.href} className="card hover:border-primary border border-transparent transition-colors">
                <div className="card-body flex items-start gap-3">
                  <span className="btn btn-icon bg-primary/15 text-primary size-10 shrink-0">
                    <Icon icon={report.icon} className="size-5" />
                  </span>
                  <div>
                    <h5 className="font-semibold">{report.title}</h5>
                    <p className="text-default-400 mt-1 text-sm">{report.description}</p>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      ))}
    </div>
  </>
)

export default Page
