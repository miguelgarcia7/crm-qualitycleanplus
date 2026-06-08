import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, usePage } from '@inertiajs/react'

const Page = () => {
  const user = (usePage().props as any).auth?.user

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="QC Minute" />

      <div className="card rounded-2xl">
        <div className="card-body p-6">
          <h4 className="mb-1 text-base font-bold">Welcome back, {user?.name}</h4>
          <p className="text-default-400">Approve timesheets and request pay increases for your contractors.</p>
        </div>
      </div>

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <Link href="/timesheets" className="card rounded-2xl transition hover:shadow-lg">
          <div className="card-body p-6">
            <h5 className="font-semibold">Timesheets</h5>
            <p className="text-default-400 text-sm">Review and approve weekly hours.</p>
            <p className="text-primary mt-2 text-sm">Open →</p>
          </div>
        </Link>
        <Link href="/pay-increases" className="card rounded-2xl transition hover:shadow-lg">
          <div className="card-body p-6">
            <h5 className="font-semibold">Pay Increases</h5>
            <p className="text-default-400 text-sm">Request a raise for a contractor.</p>
            <p className="text-primary mt-2 text-sm">Open →</p>
          </div>
        </Link>
      </div>
    </>
  )
}

export default Page
