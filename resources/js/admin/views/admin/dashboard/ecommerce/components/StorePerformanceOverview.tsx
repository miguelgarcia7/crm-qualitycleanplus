import ApexChart from '@/components/wrappers/ApexChart'
import Icon from '@/components/wrappers/Icon'
import { getColor } from '@/utils/helpers'
import { ApexOptions } from 'apexcharts'
import { useState } from 'react'

const getSalesChartOptions = (series: number[]): ApexOptions => ({
  chart: {
    height: 210,
    type: 'donut',
  },
  legend: {
    show: false,
  },
  stroke: {
    width: 0,
  },
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          total: {
            showAlways: true,
            show: true,
            formatter: (w) => `${w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0)}`,
          },
        },
      },
    },
  },
  series: series,
  labels: ['Direct', 'Affiliate', 'Sponsored'],
  colors: [getColor('chart-primary'), getColor('chart-gamma'), getColor('chart-gray')],
  dataLabels: {
    enabled: false,
  },
  responsive: [
    {
      breakpoint: 480,
      options: {
        chart: {
          width: 180,
        },
      },
    },
  ],
})

const StorePerformanceOverview = () => {
  const [isLoading, setIsLoading] = useState(false)
  const [series, setSeries] = useState<number[]>([44, 55, 41])
  const [chartKey, setChartKey] = useState(0)

  const refreshData = () => {
    setIsLoading(true)

    setTimeout(() => {
      // simulate API refresh
      setSeries([Math.floor(Math.random() * 50) + 10, Math.floor(Math.random() * 50) + 10, Math.floor(Math.random() * 50) + 10])
      setChartKey((prev) => prev + 1)
      setIsLoading(false)
    }, 1500)
  }
  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">Store Performance Analytics</h4>
        <div>
          <button onClick={refreshData} className="btn btn-sm border-default-300 hover:border-default-400 text-default-800">
            <Icon icon="refresh" /> Refresh
          </button>
        </div>
      </div>
      <div className="card-body">
        <div>
          <ApexChart key={chartKey} type="donut" height={210} series={series} getOptions={() => getSalesChartOptions(series)} />
        </div>
        <div className="text-center mb-1.25">
          <span className="badge border border-default-300 text-dark p-1.25 px-2.5 rounded-full text-xs font-bold">
            <Icon icon="star-filled" className="text-danger me-1" /> POOR SALES
          </span>
        </div>
        {isLoading && (
          <div className="card-overlay flex!">
            <div className="size-8 animate-spin rounded-full border-3 border-primary border-t-transparent rounded-[999px] text-primary"></div>
          </div>
        )}
      </div>
    </div>
  )
}

export default StorePerformanceOverview
