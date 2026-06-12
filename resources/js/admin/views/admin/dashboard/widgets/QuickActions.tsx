import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

export type QuickAction = {
  label: string
  href: string
  icon: string
}

/** Permission-gated shortcut buttons under the greeting. */
const QuickActions = ({ actions }: { actions: QuickAction[] }) => (
  <div className="flex flex-wrap gap-2">
    {actions.map((action) => (
      <Link
        key={action.href}
        href={action.href}
        className="btn btn-sm btn-light hover:text-primary"
      >
        <Icon icon={action.icon} className="me-1.5 size-4" />
        {action.label}
      </Link>
    ))}
  </div>
)

export default QuickActions
