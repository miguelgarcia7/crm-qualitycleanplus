import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, usePage } from '@inertiajs/react'

type Props = { pendingTasks?: number }

const Page = ({ pendingTasks = 0 }: Props) => {
  const user = (usePage().props as any).auth?.user

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="Back Office" />

      <div className="grid gap-4 md:grid-cols-3">
        <div className="card rounded-2xl md:col-span-2">
          <div className="card-body p-6">
            <h4 className="mb-1 text-base font-bold">Welcome back, {user?.name}</h4>
            <p className="text-default-400">
              Property Bible, work orders, time &amp; invoicing, and the workflow + inventory system are in place.
              Role-specific dashboards arrive in a later phase.
            </p>
          </div>
        </div>

        <Link href="/admin/tasks" className="card rounded-2xl transition hover:shadow-lg">
          <div className="card-body p-6">
            <p className="text-default-400 text-sm uppercase">My pending tasks</p>
            <h2 className="mt-1 text-3xl font-bold">{pendingTasks}</h2>
            <p className="text-primary mt-2 text-sm">View My Tasks →</p>
          </div>
        </Link>
      </div>
    </>
  )
}

export default Page
