import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'
import { ReactNode } from 'react'

type Props = {
  title: string
  description?: string
  /** Sits at the right of the header, e.g. a "14 waiting" badge. */
  aside?: ReactNode
  footer?: ReactNode
  className?: string
  /** Rows sit flush with the card edges (tables), not inside the body padding. */
  flush?: boolean
  children: ReactNode
}

/**
 * A titled dashboard card. It fills its grid cell, so cards in one row share a
 * height, and the footer stays pinned to the bottom.
 */
const DashCard = ({ title, description, aside, footer, className, flush, children }: Props) => (
  <section className={cn('card mb-0 flex h-full flex-col', className)}>
    <div className="card-header flex items-start justify-between gap-3">
      <div>
        <h4 className="card-title">{title}</h4>
        {/* default-500, not 400: 400 is under 4.5:1 on the card. */}
        {description && <p className="text-default-500 mt-1 text-sm">{description}</p>}
      </div>
      {aside}
    </div>
    <div className={cn('flex-1', flush ? '' : 'card-body !py-2')}>{children}</div>
    {footer && <div className="card-footer mt-auto !py-3.5 text-sm">{footer}</div>}
  </section>
)

/** "View all 9 waiting →" */
export const ViewAll = ({ href, children }: { href: string; children: ReactNode }) => (
  <Link href={href} className="text-primary font-medium">
    {children} →
  </Link>
)

/** One line in a dashboard list: what it is on the left, its next action on the right. */
export const DashRow = ({ children, end, top }: { children: ReactNode; end?: ReactNode; top?: boolean }) => (
  <div className={cn('border-default-200 flex gap-3 border-b border-dashed py-3 last:border-0', top ? 'items-start' : 'items-center')}>
    <div className="min-w-0 flex-1">{children}</div>
    {end && <div className="flex shrink-0 items-center gap-3 text-end">{end}</div>}
  </div>
)

/** Shown in place of a list that has nothing in it. */
export const DashEmpty = ({ children }: { children: ReactNode }) => (
  <p className="text-default-400 py-4 text-sm">{children}</p>
)

export default DashCard
