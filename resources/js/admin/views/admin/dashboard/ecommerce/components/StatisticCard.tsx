import { CountUp } from '@/components/wrappers/CountUp'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { StatType } from './data'

const StatisticCard = ({ stat }: { stat: StatType }) => {
  const { title, value, prefix, suffix, change, icon } = stat
  return (
    <div className="card h-full">
      <div className="card-body">
        <div className="flex justify-between items-start">
          <div>
            <h5 className="text-default-400 text-sm uppercase mb-2 font-medium" title="Number of Orders">
              {title}
            </h5>
            <h3 className="my-5 py-1.25 text-xl">
              <CountUp start={0} end={value} prefix={prefix ?? ''} suffix={suffix ?? ''} duration={1} decimals={Number.isInteger(value) ? 0 : 2} />
            </h3>
            <p className="text-default-400 text-sm flex items-center gap-3.25">
              <span className={cn('flex items-center gap-1', change > 0 ? 'text-success' : 'text-danger')}>
                {change > 0 ? <Icon icon="arrow-up" /> : <Icon icon="arrow-down" />}
                {Math.abs(change)}%
              </span>
              <span>Since last month</span>
            </p>
          </div>
          <div>
            <div className="size-9 bg-primary/15 text-primary rounded-full flex justify-center items-center">
              <Icon icon={icon} className="size-5.5" />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default StatisticCard
