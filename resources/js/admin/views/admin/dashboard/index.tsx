import BaseLayout from '@/layouts/BaseLayout'
import { Head, Link, router, usePage } from '@inertiajs/react'

const Page = () => {
  const user = (usePage().props as any).auth?.user

  return (
    <div className="min-h-screen p-8">
      <Head title="Back Office · Dashboard" />
      <header className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">QCP Back Office</h1>
          <p className="text-default-400 text-sm">Signed in as {user?.name} ({user?.email})</p>
        </div>
        <nav className="flex items-center gap-4 text-sm">
          <Link href="/admin/settings/profile" className="text-primary font-semibold">Settings</Link>
          <button onClick={() => router.post('/logout')} className="text-danger font-semibold">Sign out</button>
        </nav>
      </header>

      <div className="card rounded-2xl">
        <div className="card-body p-8">
          <h2 className="mb-1 text-base font-semibold">Dashboard</h2>
          <p className="text-default-400">Foundation is in place. Property Bible, work orders, workflows, and the rest land in later phases.</p>
        </div>
      </div>
    </div>
  )
}

Page.layout = (page: React.ReactNode) => <BaseLayout>{page}</BaseLayout>

export default Page
