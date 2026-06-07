import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, usePage } from '@inertiajs/react'

const Page = () => {
  const user = (usePage().props as any).auth?.user

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="QC Minute" />

      <div className="card rounded-2xl">
        <div className="card-body p-6">
          <h4 className="mb-1 text-base font-bold">Welcome back, {user?.name}</h4>
          <p className="text-default-400">Time tracking, timesheets, and invoices arrive in Phase 03.</p>
        </div>
      </div>
    </>
  )
}

export default Page
