import { cn } from '@/utils/helpers'
import Icon from '../wrappers/Icon'

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

const TablePagination = ({ totalItems, start, end, itemsName = 'items', showInfo, previousPage, canPreviousPage, pageCount, pageIndex, setPageIndex, nextPage, canNextPage }: TablePaginationProps) => {
  return (
    <div className={cn('items-center w-full flex text-center text-sm-start', showInfo ? 'justify-between' : 'justify-end')}>
      {showInfo && (
        <div className="text-default-400">
          Showing <span className="fw-semibold">{start}</span> to <span className="fw-semibold">{end}</span> of <span className="fw-semibold">{totalItems}</span> {itemsName}
        </div>
      )}
      <div className="mt-sm-0">
        <div>
          <ul className="pagination pagination-boxed pagination-sm flex mb-0 justify-center">
            <li className="page-item">
              <button className="page-link" onClick={() => previousPage()} disabled={!canPreviousPage}>
                <span>
                  <Icon icon="chevron-left" />
                </span>
              </button>
            </li>

            {Array.from({ length: pageCount }).map((_, index) => (
              <li key={index} className={`page-item ${pageIndex === index ? 'active' : ''}`}>
                <button className="page-link" onClick={() => setPageIndex(index)}>
                  {index + 1}
                </button>
              </li>
            ))}

            <li className="page-item">
              <button className="page-link" onClick={() => nextPage()} disabled={!canNextPage}>
                <Icon icon="chevron-right" />
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>
  )
}

export default TablePagination
