import Pagination from '@/components/table/Pagination'
import { cn } from '@/utils/helpers'

export type TablePaginationProps = {
  totalItems: number
  start: number
  end: number
  itemsName?: string
  showInfo?: boolean
  // Pagination control props
  previousPage: () => void
  canPreviousPage: boolean
  pageCount: number
  pageIndex: number
  setPageIndex: (index: number) => void
  nextPage: () => void
  canNextPage: boolean
}

/**
 * Client-side adapter: TanStack's 0-based pageIndex in, 1-based pages out.
 *
 * Props are unchanged from before so every list page picks up the windowed
 * control without edits. previousPage/nextPage/canPreviousPage/canNextPage are
 * kept for compatibility but no longer used — setPageIndex covers every move,
 * and Pagination derives its own disabled states.
 */
const TablePagination = ({ totalItems, start, end, itemsName = 'items', showInfo, pageCount, pageIndex, setPageIndex }: TablePaginationProps) => {
  return (
    // Stacked on a phone — the count and an eleven-control strip do not share
    // a 375px row. `text-sm-start` was a Bootstrap leftover that did nothing.
    <div
      className={cn(
        'flex w-full flex-col items-center gap-3 text-center sm:flex-row sm:text-start',
        showInfo ? 'sm:justify-between' : 'sm:justify-end',
      )}>
      {showInfo && (
        <div className="text-default-400">
          Showing <span className="font-semibold">{start}</span> to <span className="font-semibold">{end}</span> of{' '}
          <span className="font-semibold">{totalItems}</span> {itemsName}
        </div>
      )}

      <Pagination currentPage={pageIndex + 1} lastPage={pageCount} onPageChange={(page) => setPageIndex(page - 1)} />
    </div>
  )
}

export default TablePagination
