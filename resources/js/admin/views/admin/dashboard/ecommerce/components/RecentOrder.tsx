import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn, toPascalCase } from '@/utils/helpers'
import { createColumnHelper, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, SortingState, useReactTable } from '@tanstack/react-table'
import { useState } from 'react'
import { orderData, OrderType } from './data'

const columnHelper = createColumnHelper<OrderType>()

const RecentOrder = () => {
  const [data] = useState<OrderType[]>(orderData)
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({
    pageIndex: 0,
    pageSize: 5,
  })

  const columns = [
    columnHelper.accessor('id', {
      header: '#ID',
    }),

    columnHelper.accessor('customer', {
      header: 'Customer',
      cell: ({ row }) => (
        <>
          <h5 className="font-semibold">{row.original.customer.name}</h5>
          <span className="text-default-400 text-xs">{row.original.customer.email}</span>
        </>
      ),
    }),

    columnHelper.accessor('date', {
      header: 'Date',
    }),

    columnHelper.accessor('amount', {
      header: 'Amount',
    }),

    columnHelper.accessor('payment', {
      header: 'Payment',
    }),

    columnHelper.accessor('status', {
      header: 'Status',
      cell: ({ row }) => {
        return (
          <span
            className={cn('badge', {
              'bg-success/15 text-success': row.original.status === 'completed',
              'bg-warning/15 text-warning': row.original.status === 'pending',
              'bg-danger/15 text-danger': row.original.status === 'cancelled',
            })}
          >
            {toPascalCase(row.original.status)}
          </span>
        )
      },
    }),
  ]

  const table = useReactTable({
    data,
    columns,
    state: { sorting, pagination },
    onSortingChange: setSorting,
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  })

  const pageIndex = table.getState().pagination.pageIndex
  const pageSize = table.getState().pagination.pageSize
  const totalItems = table.getFilteredRowModel().rows.length

  const start = pageIndex * pageSize + 1
  const end = Math.min(start + pageSize - 1, totalItems)

  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">
          Recent Orders <span className="text-default-400 text-sm font-normal ms-1">(186.25k Transactions)</span>
        </h4>
        <div>
          <button className="btn btn-sm border-default-300 hover:border-default-400 font-semibold me-1">
            <Icon icon="cloud-upload" /> Export
          </button>
          <button className="btn btn-sm bg-light hover:text-primary font-semibold">
            <Icon icon="download" /> Import
          </button>
        </div>
      </div>
      <div className="card-body p-0">
        <DataTable<OrderType> table={table} emptyMessage="No orders found" className="table-centered table-hover" />
      </div>
      <div className="card-footer">
        <TablePagination
          totalItems={totalItems}
          start={start}
          end={end}
          itemsName="orders"
          showInfo
          previousPage={table.previousPage}
          canPreviousPage={table.getCanPreviousPage()}
          pageCount={table.getPageCount()}
          pageIndex={pageIndex}
          setPageIndex={table.setPageIndex}
          nextPage={table.nextPage}
          canNextPage={table.getCanNextPage()}
        />
      </div>
    </div>
  )
}

export default RecentOrder
