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
    <div className={cn('text-sm-start flex w-full items-center text-center', showInfo ? 'justify-between' : 'justify-end')}>
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
