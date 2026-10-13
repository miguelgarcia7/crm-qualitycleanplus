import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'

export type PtoBalance = { bucket: string; label: string; available: number; pending: number; allotted: number }

/** Each bucket keeps its icon and color everywhere it appears. */
const BUCKETS: Record<string, { icon: string; ring: string; bar: string }> = {
  vacation: { icon: 'calendar', ring: 'border-info bg-info/75', bar: 'bg-info' },
  scheduled: { icon: 'calendar-plus', ring: 'border-success bg-success/75', bar: 'bg-success' },
  unscheduled: { icon: 'clock', ring: 'border-warning bg-warning/75', bar: 'bg-warning' },
}

const hours = (h: number) => `${Number.isInteger(h) ? h : h.toFixed(1)} h`

/**
 * One time-off bucket: hours left, and a bar of how much of the year's
 * allowance is left. From the template's statistics Overview card
 * (widgets/statistics, row 3). `framed` draws it as its own card; inside
 * another card it's a bordered tile.
 */
const BalanceCard = ({ balance, framed = true }: { balance: PtoBalance; framed?: boolean }) => {
  const look = BUCKETS[balance.bucket] ?? BUCKETS.vacation
  const percent = balance.allotted > 0 ? Math.round((balance.available / balance.allotted) * 100) : 0

  return (
    <div className={framed ? 'card mb-0' : 'border-default-300 rounded-xl border'}>
      <div className={framed ? 'card-body' : 'p-4'}>
        <div className="flex items-start justify-between">
          <span className={cn('flex size-11 items-center justify-center rounded-full border-3 text-white', look.ring)} aria-hidden="true">
            <Icon icon={look.icon} className="text-2xl" />
          </span>
          <div className="text-end">
            <h4 className="text-default-900 text-lg font-semibold tabular-nums">{hours(balance.available)}</h4>
            <p className="text-default-500">{balance.label}</p>
          </div>
        </div>
        <div className={framed ? 'mt-7' : 'mt-5'}>
          <div className="mb-1.5 flex justify-between gap-2">
            <span className="text-default-500 text-xs font-semibold tracking-wide uppercase">
              Left{balance.pending > 0 && ` · ${hours(balance.pending)} pending`}
            </span>
            <span className="text-default-500 text-sm tabular-nums">
              {hours(balance.available)} of {hours(balance.allotted)}
            </span>
          </div>
          <div
            className="bg-default-200 flex h-1.5 w-full overflow-hidden rounded-full"
            role="progressbar"
            aria-label={`${balance.label}: ${hours(balance.available)} left of ${hours(balance.allotted)}`}
            aria-valuenow={percent}
            aria-valuemin={0}
            aria-valuemax={100}>
            <div className={cn('rounded-full', look.bar)} style={{ width: `${percent}%` }} />
          </div>
        </div>
      </div>
    </div>
  )
}

export default BalanceCard
