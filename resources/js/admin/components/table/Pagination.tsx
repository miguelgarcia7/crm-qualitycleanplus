import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'

/** A rendered slot: either a page number or an elided run of pages. */
type Slot = number | 'gap'

/**
 * First page, last page, and a window around the current one — with gaps for
 * whatever is elided in between.
 *
 * Rendering every page was fine at three pages and unusable at 179, which is
 * where the timesheet list already sits. `siblings` is how many pages flank the
 * current one; the slot count keeps the control a stable width as you move
 * through it, so the buttons do not shuffle under the cursor.
 */
export const pageWindow = (current: number, last: number, siblings = 1): Slot[] => {
  const span = (from: number, to: number): number[] => Array.from({ length: to - from + 1 }, (_, i) => from + i)

  // first + last + current + siblings on both sides + two gaps
  const slots = siblings * 2 + 5

  if (last <= slots) return span(1, last)

  const left = Math.max(current - siblings, 1)
  const right = Math.min(current + siblings, last)
  const gapLeft = left > 2
  const gapRight = right < last - 1

  const slotted: Slot[] =
    !gapLeft && gapRight
      ? [...span(1, slots - 2), 'gap', last]
      : gapLeft && !gapRight
        ? [1, 'gap', ...span(last - (slots - 3), last)]
        : [1, 'gap', ...span(left, right), 'gap', last]

  // A gap standing in for a single page is worse than the page itself.
  return slotted.map((slot, i) => {
    if (slot !== 'gap') return slot
    const before = slotted[i - 1]
    const after = slotted[i + 1]

    return typeof before === 'number' && typeof after === 'number' && after - before === 2 ? before + 1 : slot
  })
}

export type PaginationProps = {
  /** 1-based. */
  currentPage: number
  lastPage: number
  onPageChange: (page: number) => void
  siblings?: number
}

/**
 * Page control, presentational only — it knows the current page and the last
 * one, and nothing about where the rows came from. Both the client-side
 * (TanStack) and server-side (Laravel paginator) adapters render this, so the
 * windowing lives in exactly one place.
 */
const Pagination = ({ currentPage, lastPage, onPageChange, siblings = 1 }: PaginationProps) => {
  if (lastPage <= 1) return null

  const go = (page: number) => onPageChange(Math.min(Math.max(page, 1), lastPage))
  const atStart = currentPage <= 1
  const atEnd = currentPage >= lastPage

  return (
    <nav aria-label="Pagination">
      <ul className="pagination pagination-boxed pagination-sm mb-0 flex justify-center">
        <li className="page-item">
          <button className="page-link" onClick={() => go(1)} disabled={atStart} aria-label="First page">
            <Icon icon="chevrons-left" />
          </button>
        </li>
        <li className="page-item">
          <button className="page-link" onClick={() => go(currentPage - 1)} disabled={atStart} aria-label="Previous page">
            <Icon icon="chevron-left" />
          </button>
        </li>

        {pageWindow(currentPage, lastPage, siblings).map((slot, i) =>
          slot === 'gap' ? (
            <li key={`gap-${i}`} className="page-item" aria-hidden="true">
              <span className="page-link pointer-events-none">…</span>
            </li>
          ) : (
            <li key={slot} className={cn('page-item', currentPage === slot && 'active')}>
              <button
                className="page-link"
                onClick={() => go(slot)}
                aria-label={`Page ${slot}`}
                aria-current={currentPage === slot ? 'page' : undefined}>
                {slot}
              </button>
            </li>
          ),
        )}

        <li className="page-item">
          <button className="page-link" onClick={() => go(currentPage + 1)} disabled={atEnd} aria-label="Next page">
            <Icon icon="chevron-right" />
          </button>
        </li>
        <li className="page-item">
          <button className="page-link" onClick={() => go(lastPage)} disabled={atEnd} aria-label="Last page">
            <Icon icon="chevrons-right" />
          </button>
        </li>
      </ul>
    </nav>
  )
}

export default Pagination
