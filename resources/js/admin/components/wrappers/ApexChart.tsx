import ReactApexCharts from 'react-apexcharts'
import { type ApexOptions } from 'apexcharts'
import { useMemo } from 'react'

import { useLayoutContext } from '@/context/useLayoutContext'



type PropsType = {
  type?: ApexChart['type']
  height?: number | string
  width?: number | string
  getOptions: () => ApexOptions
  series?: ApexOptions['series']
  className?: string
}

const ApexChart = ({ type, height, width = '100%', getOptions, series, className }: PropsType) => {
  const { skin, theme } = useLayoutContext()

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const options = useMemo(() => getOptions(), [skin, theme, getOptions])

  return <ReactApexCharts type={type ?? options.chart?.type} height={height ?? options.chart?.height} width={width ?? options.chart?.width} options={options} series={series ?? options.series} className={`apex-charts ${className || ''}`} />
}

export default ApexChart
