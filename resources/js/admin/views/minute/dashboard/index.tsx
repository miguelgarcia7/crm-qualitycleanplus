import PageBreadcrumb from '@/components/PageBreadcrumb'
import { hasPermission } from '@/layouts/components/useMenuItems'
import { Head, Link, usePage } from '@inertiajs/react'

// Shortcut cards, gated like the sidebar (each permission is the one its page
// enforces) — contractors sign in here too, and shouldn't be offered PM pages.
const cards = [
  { href: '/timesheets', title: 'Timesheets', desc: 'Review and approve weekly hours.', permission: 'timesheets.view_live' },
  { href: '/invoices', title: 'Invoices', desc: 'View and download invoices for your property.', permission: 'invoices.view' },
  { href: '/contractors', title: 'Contractors', desc: 'Who is placed with you, and direct-hire eligibility.', permission: 'people.contractors.view' },
  { href: '/pay-increases', title: 'Pay Increases', desc: 'Request a raise for a contractor.', permission: 'workflows.pay_increase.initiate' },
  { href: '/staffing-requests', title: 'Staffing Requests', desc: 'Ask for more contractors at your property.', permission: 'workflows.more_staff.initiate' },
  { href: '/my-info', title: 'My Info', desc: 'Request a change to your name, email, or phone.', permission: 'people.own_profile.request_change' },
  { href: '/kb', title: 'Knowledge Base', desc: 'Guides and answers for day-to-day work.', permission: 'kb.articles.view' },
]

/** QC Minute home for everyone but property managers (who get their own dashboard). */
const Page = () => {
  const props = usePage().props as { auth?: { user?: { name?: string }; permissions?: string[] } }
  const user = props.auth?.user
  const permissions = props.auth?.permissions ?? []

  return (
    <>
      <Head title="Dashboard" />
      <PageBreadcrumb title="Dashboard" subtitle="QC Minute" />

      <div className="card rounded-2xl">
        <div className="card-body p-6">
          <h4 className="mb-1 text-base font-bold">Welcome back, {user?.name}</h4>
          <p className="text-default-500">Everything you can do here, in one place.</p>
        </div>
      </div>

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        {cards.filter((card) => hasPermission(card.permission, permissions)).map((card) => (
          <Link key={card.href} href={card.href} className="card rounded-2xl transition hover:shadow-lg">
            <div className="card-body p-6">
              <h5 className="font-semibold">{card.title}</h5>
              <p className="text-default-400 text-sm">{card.desc}</p>
              <p className="text-primary mt-2 text-sm">Open →</p>
            </div>
          </Link>
        ))}
      </div>
    </>
  )
}

export default Page
