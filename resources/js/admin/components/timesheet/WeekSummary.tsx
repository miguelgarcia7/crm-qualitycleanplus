import { cn } from '@/utils/helpers'
import { ReactNode } from 'react'

/** Where a week sits in the approval flow (ADR-0007), shared by the grid and the property overview. */
export const StatusTrack = ({ status, label }: { status: string; label: string }) => {
  if (status === 'voided') return <span className="badge badge-label bg-light text-default-600">{label}</span>

  const steps = ['Draft', 'Pending approval', 'Approved', 'Invoiced']
  const at = { draft: 0, declined: 0, pending_approval: 1, approved: 2, invoiced: 3, invoice_sent: 3 }[status] ?? 0

  return (
    <ol className="flex flex-wrap items-center gap-x-1 gap-y-1 text-xs" aria-label="Timesheet status">
      {steps.map((step, i) => (
        <li key={step} className={cn('flex items-center gap-1.5 whitespace-nowrap', i < at && 'text-success', i === at && 'text-default-900 font-semibold', i > at && 'text-default-500')}>
          {i > 0 && <span className="bg-default-300 me-0.5 h-px w-4" />}
          <span
            className={cn(
              'size-2 rounded-full border-[1.5px] border-current',
              i < at && 'bg-current',
              i === at && (status === 'declined' ? 'border-danger bg-danger' : 'border-secondary bg-secondary ring-secondary/20 ring-3'),
            )}
          />
          {i === at ? label : step}
        </li>
      ))}
    </ol>
  )
}

/** One figure in a week's summary bar (hours, overtime, payroll, …). */
export const Stat = ({ label, value, note, valueClass, className }: { label: string; value: string; note: ReactNode; valueClass?: string; className?: string }) => (
  <div className={cn('border-default-300 min-w-0 border-b px-5 py-3.5 last:border-b-0 lg:border-b-0 lg:not-first:border-s [&:nth-child(even)]:border-s', className)}>
    <div className="text-default-500 text-[11px] font-medium tracking-wider uppercase">{label}</div>
    <div className={cn('text-default-900 text-xl font-semibold tabular-nums', valueClass)}>{value}</div>
    <div className="text-default-500 text-xs">{note}</div>
  </div>
)
