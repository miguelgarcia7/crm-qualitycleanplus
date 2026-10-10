import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'

// The System Reference pages, in the order their tabs show. A topic joins
// this list when its page is built.
const PAGES = [
  { href: '/admin/system', label: 'Overview' },
  { href: '/admin/system/roles', label: 'Roles & permissions' },
]

const ReferenceNav = ({ current }: { current: string }) => (
  <nav aria-label="System reference pages" className="border-default-300 mb-5 flex flex-wrap gap-1 border-b">
    {PAGES.map((p) => (
      <Link
        key={p.href}
        href={p.href}
        aria-current={p.href === current ? 'page' : undefined}
        className={cn(
          '-mb-px border-b-2 px-4 py-2.5 font-medium',
          p.href === current ? 'border-primary text-default-900' : 'text-default-500 hover:text-default-900 border-transparent',
        )}
      >
        {p.label}
      </Link>
    ))}
  </nav>
)

export default ReferenceNav
