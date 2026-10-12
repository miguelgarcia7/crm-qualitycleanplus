import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import { cn } from '@/utils/helpers'

export type LowStock = { rows: { id: number; name: string; detail: string; urgent: boolean }[]; total: number; onOrder: number }

const plural = (n: number, one: string, many: string) => `${n.toLocaleString()} ${n === 1 ? one : many}`

/** Stock at or below its reorder level, lowest first, and what is on order. */
const LowStockCard = ({ data }: { data: LowStock }) => (
  <DashCard
    title="Low stock"
    description="At or below the reorder level, lowest first."
    footer={
      <div className="flex w-full flex-wrap items-center justify-between gap-2">
        <ViewAll href="/admin/inventory">Inventory</ViewAll>
        {data.onOrder > 0 && <span className="text-default-500">{plural(data.onOrder, 'order', 'orders')} on the way</span>}
      </div>
    }>
    {data.rows.length === 0 ? (
      <DashEmpty>Everything is above its reorder level.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow key={row.id} end={<span className={cn('badge badge-label', row.urgent ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning')}>Low</span>}>
          <div className="text-default-900 font-medium">{row.name}</div>
          <div className="text-default-500 text-sm">{row.detail}</div>
        </DashRow>
      ))
    )}
  </DashCard>
)

export default LowStockCard
