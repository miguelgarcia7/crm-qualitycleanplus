import ApexChart from '@/components/wrappers/ApexChart'
import { type ApexOptions } from 'apexcharts'

export type Trend = {
  title: string
  categories: string[]
  series: { name: string; data: number[] }[]
  type?: 'area' | 'bar'
  valuePrefix?: string
  valueSuffix?: string
}

const TrendChart = ({ trend }: { trend: Trend }) => {
  const prefix = trend.valuePrefix ?? ''
  const suffix = trend.valueSuffix ?? ''
  const fmt = (v: number) => `${prefix}${Math.round(v).toLocaleString()}${suffix}`

  const getOptions = (): ApexOptions => ({
    chart: { type: trend.type ?? 'area', height: 280, toolbar: { show: false } },
    stroke: { curve: 'smooth', width: trend.type === 'bar' ? 0 : 2 },
    fill: trend.type === 'bar' ? { opacity: 1 } : { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.05 } },
    dataLabels: { enabled: false },
    grid: { strokeDashArray: 4 },
    xaxis: { categories: trend.categories, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { formatter: fmt } },
    tooltip: { y: { formatter: fmt } },
    plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
  })

  return (
    <div className="card h-full">
      <div className="card-header p-5 pb-0">
        <h4 className="card-title">{trend.title}</h4>
      </div>
      <div className="card-body p-3">
        <ApexChart getOptions={getOptions} series={trend.series} type={trend.type ?? 'area'} height={280} />
      </div>
    </div>
  )
}

export default TrendChart
