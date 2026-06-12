import { CountUp } from '@/components/wrappers/CountUp'
import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

export type Stat = {
  title: string
  value: number | string
  icon?: string
  prefix?: string
  suffix?: string
  sublabel?: string
  href?: string
  tone?: 'default' | 'warning' | 'danger' | 'success'
  change?: number | null
  changeLabel?: string
}

const toneClasses: Record<NonNullable<Stat['tone']>, string> = {
  default: 'bg-primary/15 text-primary',
  warning: 'bg-warning/15 text-warning',
  danger: 'bg-danger/15 text-danger',
  success: 'bg-success/15 text-success',
}

const StatCard = ({ stat }: { stat: Stat }) => {
  const body = (
    <div className="card-body p-5">
      <div className="flex items-start justify-between">
        <div>
          <h5 className="text-default-400 mb-2 text-sm font-medium uppercase">{stat.title}</h5>
          <h3 className="text-2xl font-semibold">
            {typeof stat.value === 'number' ? (
              <CountUp start={0} end={stat.value} prefix={stat.prefix ?? ''} suffix={stat.suffix ?? ''} duration={1} decimals={Number.isInteger(stat.value) ? 0 : 2} />
            ) : (
              <>
                {stat.prefix}
                {stat.value}
                {stat.suffix}
              </>
            )}
          </h3>
          {stat.sublabel && <p className="text-default-400 mt-2 text-sm">{stat.sublabel}</p>}
          {typeof stat.change === 'number' && (
            <p className="text-default-400 mt-2 flex items-center gap-1.5 text-sm">
              <span className={`flex items-center gap-0.5 font-medium ${stat.change >= 0 ? 'text-success' : 'text-danger'}`}>
                <Icon icon={stat.change >= 0 ? 'arrow-up' : 'arrow-down'} className="size-3.5" />
                {Math.abs(stat.change)}%
              </span>
              {stat.changeLabel}
            </p>
          )}
          {stat.href && stat.change == null && <p className="text-primary mt-2 text-sm">View →</p>}
        </div>
        {stat.icon && (
          <div className={`flex size-9 items-center justify-center rounded-full ${toneClasses[stat.tone ?? 'default']}`}>
            <Icon icon={stat.icon} className="size-5" />
          </div>
        )}
      </div>
    </div>
  )

  const className = 'card h-full rounded-2xl'

  return stat.href ? (
    <Link href={stat.href} className={`${className} transition hover:shadow-lg`}>
      {body}
    </Link>
  ) : (
    <div className={className}>{body}</div>
  )
}

export default StatCard
