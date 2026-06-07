import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import maintenanceSvg from '@/images/admin/maintenance.svg'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'


const Page = () => {
  return (
    <div className="flex min-h-screen items-center">
      <div className="container">
        <div className="flex justify-center lg:p-0 p-12.5">
          <div className="w-full lg:w-1/2">
            <div className="absolute end-0 top-0">
              <img src={authCard} alt="auth-card-bg" />
            </div>

            <div className="absolute start-0 bottom-0 rotate-180">
              <img src={authCard} alt="auth-card-bg" />
            </div>
            <div className="card rounded-2xl">
              <div className="card-body p-12.5">
                <div className="flex flex-col items-center justify-center text-center">
                  <AuthLogo />
                </div>

                <div className="p-3 text-center">
                  <img src={maintenanceSvg} alt="Maintenance" className="mx-auto md:size-64" />
                  <h3 className="mb-2 text-xl font-bold uppercase">Site Under Maintenance</h3>
                  <p className="text-default-400">
                    We’re currently performing scheduled maintenance.
                    <br />
                    Please check back soon.
                  </p>
                  <div className="mt-8 flex items-center justify-center gap-2 flex-wrap">
                    <button className="btn bg-primary text-white hover:bg-primary-hover">Call Support</button>
                    <button className="btn bg-info text-white hover:bg-info-hover">Send Email</button>
                  </div>
                </div>

                <p className="text-default-400 mt-18 text-center">
                  &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}


Page.layout = (page: React.ReactNode) => (
  <BaseLayout>{page}</BaseLayout>
)

export default Page
