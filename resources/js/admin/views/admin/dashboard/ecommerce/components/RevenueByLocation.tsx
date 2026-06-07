import BaseVectorMap from '@/components/wrappers/BaseVectorMap'
import Icon from '@/components/wrappers/Icon'
import { getColor } from '@/utils/helpers'
import { Link } from '@inertiajs/react'


export const getWorldMarkerLineOptions = () => ({
  map: 'world_merc',
  zoomOnScroll: false,
  zoomButtons: false,
  markers: [
    { name: 'Australia', coords: [-25.2744, 133.7751] },
    { name: 'India', coords: [20.5937, 78.9629] },
    { name: 'Japan', coords: [36.2048, 138.2529] },
    { name: 'South Africa', coords: [-30.5595, 22.9375] },
    { name: 'Germany', coords: [51.1657, 10.4515] },
    { name: 'United Kingdom', coords: [55.3781, -3.436] },
    { name: 'Mexico', coords: [23.6345, -102.5528] },
    { name: 'Argentina', coords: [-38.4161, -63.6167] },
    { name: 'Saudi Arabia', coords: [23.8859, 45.0792] },
    { name: 'Indonesia', coords: [-0.7893, 113.9213] },
  ],
  lines: [
    { from: 'India', to: 'Australia' },
    { from: 'Japan', to: 'Germany' },
    { from: 'Mexico', to: 'United Kingdom' },
    { from: 'Argentina', to: 'South Africa' },
    { from: 'Saudi Arabia', to: 'India' },
    { from: 'Indonesia', to: 'Japan' },
    { from: 'United Kingdom', to: 'Germany' },
    { from: 'Australia', to: 'Indonesia' },
  ],
  regionStyle: {
    initial: {
      stroke: '#aab9d14d',
      strokeWidth: 0.25,
      fill: '#aab9d14d',
      fillOpacity: 1,
    },
  },
  markerStyle: {
    initial: { fill: getColor('secondary') },
    selected: { fill: getColor('secondary') },
  },
  lineStyle: {
    animation: true,
    strokeDasharray: '1 2 3 4 5 6',
  },
})

const RevenueByLocation = () => {
  return (
    <div className="card h-full">
      <div className="card-header">
        <h4 className="card-title">Revenue By Locations</h4>
        <div className="hs-dropdown [--placement:bottom-right] ms-auto">
          <Link href="" className="hs-dropdown-toggle btn btn-sm btn-icon border-default-300 hover:text-primary">
            <Icon icon="dots-vertical" className="text-base" />
          </Link>
          <ul className="hs-dropdown-menu">
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="map" />
                View Full Map
              </Link>
            </li>
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="download" /> Export Revenue Data
              </Link>
            </li>
            <li>
              <Link className="dropdown-item" href="">
                <Icon icon="filter-2" /> Filter By Region
              </Link>
            </li>
            <li>
              <hr className="dropdown-divider" />
            </li>
            <li>
              <Link className="dropdown-item text-danger" href="">
                <Icon icon="trash" /> Remove Widget
              </Link>
            </li>
          </ul>
        </div>
      </div>
      <div className="card-body pt-1.25">
        <BaseVectorMap id="revenue-by-location" options={getWorldMarkerLineOptions()} style={{ height: 206 }} />
        <div className="mt-5">
          <div className="p-2.5 mb-5 border border-dashed border-default-300 rounded">
            <div className="md:flex items-center md:justify-between gap-2.5">
              <div className="flex items-center gap-2.5">
                <div>
                  <div className="size-12 flex justify-center items-center rounded-full bg-warning/15 text-warning">
                    <Icon icon="medal" className="size-7" />
                  </div>
                </div>
                <div>
                  <h5 className="text-md">Congratulations !...</h5>
                  <p className="text-sm text-default-400">You&apos;ve just hit a new record..</p>
                </div>
              </div>
              <div className="ms-auto">
                <h4 className="mt-1.25 text-end text-base">25.9k</h4>
                <span className="text-default-400 text-xs uppercase">Order</span>
              </div>
            </div>
          </div>
          <div className="flex items-center mb-2.5 gap-2.5">
            <Icon icon="circle" className="text-info text-md" />
            <div>United States</div>
            <p className="ms-auto">
              <span className="font-semibold">$48.6k</span> <span className="text-default-400">Revenue</span>
            </p>
          </div>
          <div className="flex items-center mb-2.5 gap-2.5">
            <Icon icon="circle" className="text-primary text-md" />
            <div>United Kingdom</div>
            <p className="ms-auto">
              <span className="font-semibold">$26.4k</span> <span className="text-default-400">Revenue</span>
            </p>
          </div>
          <div className="flex items-center gap-2.5">
            <Icon icon="circle" className="text-secondary text-md" />
            <div>Australia</div>
            <p className="ms-auto">
              <span className="font-semibold">$18.9k</span> <span className="text-default-400">Revenue</span>
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}

export default RevenueByLocation
