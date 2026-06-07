import ApexChart from '@/components/wrappers/ApexChart'
import Icon from '@/components/wrappers/Icon'
import { getColor } from '@/utils/helpers'
import { ApexOptions } from 'apexcharts'
import { Link } from '@inertiajs/react'

const getWeeklyPerformanceChart = (): ApexOptions => ({
  series: [
    {
      data: [
        {
          x: 'Mon',
          y: [28, 45],
        },
        {
          x: 'Tue',
          y: [32, 41],
        },
        {
          x: 'Wed',
          y: [29, 78],
        },
        {
          x: 'Thu',
          y: [30, 46],
        },
        {
          x: 'Fri',
          y: [35, 41],
        },
        {
          x: 'Sat',
          y: [45, 65],
        },
        {
          x: 'Sun',
          y: [41, 56],
        },
      ],
    },
  ],
  chart: {
    height: 247,
    type: 'rangeBar',
    toolbar: {
      show: false,
    },
    zoom: {
      enabled: false,
    },
  },
  plotOptions: {
    bar: {
      horizontal: true,
      isDumbbell: true,
      dumbbellColors: [[getColor('chart-primary'), getColor('chart-primary')]],
    },
  },
  legend: {
    show: false,
  },
  fill: {
    type: 'gradient',
    gradient: {
      gradientToColors: [getColor('chart-primary'), getColor('chart-gamma'), getColor('chart-gray')],
      inverseColors: false,
      stops: [0, 100],
    },
  },
  xaxis: {
    // type: 'string',
    axisBorder: {
      show: false,
    },
  },
  yaxis: {
    axisBorder: {
      show: false,
    },
    labels: {
      offsetX: 10,
    },
  },
  grid: {
    borderColor: getColor('chart-order-color'),
    padding: {
      top: -20,
      right: 0,
      bottom: -10,
      left: 0,
    },
  },
})
const WeeklyPerformanceInsights = () => {
  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">Weekly Performance Insights</h4>
        <div className="hs-dropdown [--placement:bottom-right] ms-auto">
          <Link href="" className="hs-dropdown-toggle btn btn-sm btn-icon border-default-300 hover:text-primary">
            <Icon icon="dots-vertical" className="text-base" />
          </Link>
          <ul className="hs-dropdown-menu">
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="refresh" /> Refresh Data
              </Link>
            </li>
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="download" /> Download Report
              </Link>
            </li>
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="share" /> Share Insights
              </Link>
            </li>
            <li>
              <hr className="dropdown-divider" />
            </li>
            <li>
              <Link className="dropdown-item text-danger" href="#">
                <Icon icon="archive" /> Archive Widget
              </Link>
            </li>
          </ul>
        </div>
      </div>
      <div className="card-body">
        <div>
          <ApexChart getOptions={getWeeklyPerformanceChart} series={getWeeklyPerformanceChart().series} className="apex-charts" />
        </div>
      </div>
    </div>
  )
}

export default WeeklyPerformanceInsights
