import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'


const Page = () => {
  return (
    <>
      <div className="flex min-h-screen items-center">
        <div className="container">
          <div className="flex justify-center lg:p-0 p-12.5">
            <div className="2xl:w-4/10 md:w-1/2 sm:w-2/3 w-full">
              <div className="absolute end-0 top-0">
                <img src={authCard} alt="auth-card-bg" />
              </div>

              <div className="absolute start-0 bottom-0 rotate-180">
                <img src={authCard} alt="auth-card-bg" />
              </div>
              <div className="card rounded-2xl">
                <div className="card-body p-7.5">
                  <div className="mb-4 flex flex-col items-center justify-center text-center">
                    <AuthLogo />
                  </div>
                  <div className="p-9 text-center">
                    <div className="from-primary my-4 bg-linear-to-r to-danger bg-clip-text text-7xl font-bold text-transparent">403</div>
                    <h3 className="mb-2 text-xl font-bold uppercase">Permission Required</h3>
                    <p className="text-default-400 mx-auto">
                      You don’t have the required permissions to view this page.
                      <br />
                      Please contact your administrator or try a different account.
                    </p>
                    <div className="mt-9 flex items-center justify-center gap-1.5">
                      <Link className="btn bg-primary text-white hover:bg-primary-hover" href="/">
                        Go to Dashboard
                      </Link>
                      <button className="btn border-secondary text-secondary hover:bg-secondary hover:text-white">Back</button>
                    </div>
                  </div>
                </div>
              </div>

              <p className="text-default-400 mt-7.5 text-center">
                &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}


Page.layout = (page: React.ReactNode) => (
  <BaseLayout>{page}</BaseLayout>
)

export default Page
