import { CountUp } from '@/components/wrappers/CountUp'
import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

export type PulseStatData = {
  title: string
  value: number
  prefix?: string
  suffix?: string
  icon?: string
  tone?: 'default' | 'warning' | 'danger' | 'success'
  sublabel?: string | null
  change?: number | null
  changeLabel?: string | null
  href?: string | null
}

const toneClasses: Record<NonNullable<PulseStatData['tone']>, string> = {
  default: 'bg-primary/15 text-primary',
  warning: 'bg-warning/15 text-warning',
  danger: 'bg-danger/15 text-danger',
  success: 'bg-success/15 text-success',
}

/** One headline figure. The card is not a link — the title is. */
const PulseStat = ({ stat }: { stat: PulseStatData }) => {
  const decimals = Number.isInteger(stat.value) ? 0 : 2

  return (
    <div className="card h-full">
      <div className="card-body p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            {stat.href ? (
              <Link href={stat.href} className="text-default-400 hover:text-primary mb-2 block text-sm font-medium uppercase">
                {stat.title}
              </Link>
            ) : (
              <h5 className="text-default-400 mb-2 text-sm font-medium uppercase">{stat.title}</h5>
            )}

            <h3 className="text-2xl font-semibold">
              <CountUp start={0} end={stat.value} prefix={stat.prefix ?? ''} suffix={stat.suffix ?? ''} duration={1} decimals={decimals} />
            </h3>

            {typeof stat.change === 'number' && (
              <p className="text-default-400 mt-2 flex flex-wrap items-center gap-1.5 text-sm">
                <span className={`flex items-center gap-0.5 font-medium ${stat.change >= 0 ? 'text-success' : 'text-danger'}`}>
                  <Icon icon={stat.change >= 0 ? 'arrow-up' : 'arrow-down'} className="size-3.5" />
                  {Math.abs(stat.change)}%
                </span>
                {stat.changeLabel}
              </p>
            )}

            {stat.sublabel && stat.change == null && (
              <p className={`mt-2 text-sm ${stat.tone === 'warning' ? 'text-warning' : 'text-default-400'}`}>{stat.sublabel}</p>
            )}
          </div>

          {stat.icon && (
            <span className={`flex size-9 shrink-0 items-center justify-center rounded-full ${toneClasses[stat.tone ?? 'default']}`}>
              <Icon icon={stat.icon} className="size-5" />
            </span>
          )}
        </div>
      </div>
    </div>
  )
}

export default PulseStat
