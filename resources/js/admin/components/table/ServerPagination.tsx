import Pagination from '@/components/table/Pagination'
import { cn } from '@/utils/helpers'

/** The slice of Laravel's paginator the front end needs. */
export type PaginationMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export type ServerPaginationProps = {
  meta: PaginationMeta
  onPageChange: (page: number) => void
  itemsName?: string
  showInfo?: boolean
}

/**
 * Server-side adapter, for lists backed by a Laravel paginator.
 *
 * The counts come from the database rather than from what happens to be loaded,
 * so "of 1,787" stays honest no matter which page is on screen.
 */
const ServerPagination = ({ meta, onPageChange, itemsName = 'items', showInfo = true }: ServerPaginationProps) => {
  return (
    <div className={cn('text-sm-start flex w-full items-center text-center', showInfo ? 'justify-between' : 'justify-end')}>
      {showInfo && (
        <div className="text-default-400">
          Showing <span className="font-semibold">{meta.from ?? 0}</span> to <span className="font-semibold">{meta.to ?? 0}</span> of{' '}
          <span className="font-semibold">{meta.total.toLocaleString('en-US')}</span> {itemsName}
        </div>
      )}

      <Pagination currentPage={meta.current_page} lastPage={meta.last_page} onPageChange={onPageChange} />
    </div>
  )
}

export default ServerPagination
