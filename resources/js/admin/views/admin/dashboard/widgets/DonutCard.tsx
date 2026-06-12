import ApexChart from '@/components/wrappers/ApexChart'
import { getColor } from '@/utils/helpers'
import { type ApexOptions } from 'apexcharts'

export type Donut = {
  title: string
  labels: string[]
  series: number[]
  suffix?: string
}

const DonutCard = ({ donut }: { donut: Donut }) => {
  const suffix = donut.suffix ?? ''
  const fmt = (v: number) => `${v.toLocaleString()}${suffix}`

  const getOptions = (): ApexOptions => ({
    chart: { type: 'donut', height: 280 },
    labels: donut.labels,
    colors: [
      getColor('chart-primary'),
      getColor('chart-secondary'),
      getColor('chart-gamma'),
      getColor('chart-delta'),
      getColor('chart-zeta'),
      getColor('chart-gray'),
    ],
    legend: { position: 'bottom', markers: { size: 5 } },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    plotOptions: {
      pie: {
        donut: {
          size: '72%',
          labels: {
            show: true,
            total: {
              show: true,
              label: 'Total',
              formatter: (w) => fmt(w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0)),
            },
          },
        },
      },
    },
    tooltip: { y: { formatter: fmt } },
  })

  return (
    <div className="card h-full rounded-2xl">
      <div className="card-header p-5 pb-0">
        <h4 className="card-title">{donut.title}</h4>
      </div>
      <div className="card-body p-3">
        <ApexChart getOptions={getOptions} series={donut.series} type="donut" height={280} />
      </div>
    </div>
  )
}

export default DonutCard
