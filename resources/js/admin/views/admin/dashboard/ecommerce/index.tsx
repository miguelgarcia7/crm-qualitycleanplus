import PageBreadcrumb from '@/components/PageBreadcrumb'
import { statData } from './components/data'
import RecentActivity from './components/RecentActivity'
import RecentOrder from './components/RecentOrder'
import RevenueByLocation from './components/RevenueByLocation'
import SalesReport from './components/SalesReport'
import StatisticCard from './components/StatisticCard'
import StorePerformanceOverview from './components/StorePerformanceOverview'
import TopSellingProducts from './components/TopSellingProducts'
import UserCard from './components/UserCard'
import WeeklyPerformanceInsights from './components/WeeklyPerformanceInsights'


const Page = () => {
  return (
    <>
      <PageBreadcrumb title="eCommerce" subtitle="Dashboard" />
      <div className="grid xl:grid-cols-12 grid-cols-1 gap-base mb-base">
        <div className="xl:col-span-5">
          <div className="grid md:grid-cols-2 grid-cols-1 gap-base h-full">
            <UserCard />
            {statData.map((stat, idx) => (
              <StatisticCard stat={stat} key={idx} />
            ))}
          </div>
        </div>
        <div className="xl:col-span-7">
          <div className="grid md:grid-cols-2 grid-cols-1 gap-base h-full">
            <StorePerformanceOverview />
            <WeeklyPerformanceInsights />
          </div>
        </div>
      </div>

      <div className="grid xl:grid-cols-2 grid-cols-1 gap-base mb-base">
        <SalesReport />
        <TopSellingProducts />
      </div>

      <div className="grid xl:grid-cols-12 grid-cols-1 gap-base">
        <div className="xl:col-span-5">
          <RecentOrder />
        </div>
        <div className="xl:col-span-7">
          <div className="grid xl:grid-cols-2 grid-cols-1 gap-base h-full">
            <RevenueByLocation />
            <RecentActivity />
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
