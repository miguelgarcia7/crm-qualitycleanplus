import BaseLayout from '@/layouts/BaseLayout'
import { Head, router, usePage } from '@inertiajs/react'

const Page = () => {
  const user = (usePage().props as any).auth?.user

  return (
    <div className="min-h-screen p-8">
      <Head title="QC Minute · Dashboard" />
      <header className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">QC Minute</h1>
          <p className="text-default-400 text-sm">Signed in as {user?.name} ({user?.email})</p>
        </div>
        <button onClick={() => router.post('/logout')} className="text-danger text-sm font-semibold">Sign out</button>
      </header>

      <div className="card rounded-2xl">
        <div className="card-body p-8">
          <h2 className="mb-1 text-base font-semibold">Dashboard</h2>
          <p className="text-default-400">Time tracking, timesheets, and invoices land in Phase 03.</p>
        </div>
      </div>
    </div>
  )
}

Page.layout = (page: React.ReactNode) => <BaseLayout>{page}</BaseLayout>

export default Page
