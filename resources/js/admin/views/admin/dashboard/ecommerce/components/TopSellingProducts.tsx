import DataTable from '@/components/table/DataTable'
import TablePagination from '@/components/table/TablePagination'
import Icon from '@/components/wrappers/Icon'
import { cn, toPascalCase } from '@/utils/helpers'
import { createColumnHelper, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, SortingState, useReactTable } from '@tanstack/react-table'
import { useState } from 'react'
import { productData, ProductType } from './data'

const columnHelper = createColumnHelper<ProductType>()

const TopSellingProducts = () => {
  const [data] = useState<ProductType[]>(productData)
  const [sorting, setSorting] = useState<SortingState>([])
  const [pagination, setPagination] = useState({
    pageIndex: 0,
    pageSize: 6,
  })

  const columns = [
    columnHelper.accessor('image', {
      header: '',
      cell: ({ row }) => <img src={row.original.image} alt={row.original.name} height={36} width={36} />,
      enableSorting: false,
    }),

    columnHelper.accessor('name', {
      header: 'Product',
      cell: ({ row }) => (
        <>
          <h5>{row.original.name}</h5>
          <span className="text-default-400 text-xs">By: {row.original.brand}</span>
        </>
      ),
    }),

    columnHelper.accessor('price', {
      header: 'Price',
      cell: ({ row }) => (
        <>
          <h5>{row.original.price}</h5>
          <span className="text-default-400 text-xs">Price</span>
        </>
      ),
    }),

    columnHelper.accessor('quantity', {
      header: 'Quantity',
      cell: ({ row }) => (
        <>
          <h5>{row.original.quantity}</h5>
          <span className="text-default-400 text-xs">Quantity</span>
        </>
      ),
    }),

    columnHelper.accessor('amount', {
      header: 'Amount',
      cell: ({ row }) => (
        <>
          <h5>{row.original.amount}</h5>
          <span className="text-default-400 text-xs">Amount</span>
        </>
      ),
    }),

    columnHelper.accessor('status', {
      header: 'Status',
      cell: ({ row }) => {
        return (
          <span
            className={cn('badge px-2.5 rounded-full text-xs', {
              'bg-success/15 text-success': row.original.status === 'in-stock',
              'bg-danger/15 text-danger': row.original.status === 'out-of-stock',
              'bg-warning/15 text-warning': row.original.status === 'low-stock',
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
        <h4 className="card-title">Top Selling Products</h4>
        <div>
          <button className="btn btn-sm border-default-300 hover:border-default-400 me-1">
            <Icon icon="cloud-upload" /> Export
          </button>
          <button className="btn btn-sm bg-light hover:text-primary">
            <Icon icon="download" /> Import
          </button>
        </div>
      </div>
      <div className="card-body p-0">
        <DataTable<ProductType> table={table} emptyMessage="No products found" className="whitespace-nowrap w-full" showHeaders={false} />
      </div>

      <div className="card-footer">
        <TablePagination
          totalItems={totalItems}
          start={start}
          end={end}
          itemsName="products"
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

export default TopSellingProducts
