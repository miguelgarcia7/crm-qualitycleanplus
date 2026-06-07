import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import deleteImg from '@/images/admin/delete.png'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'

const Page = () => {
  return (
    <div className="flex min-h-screen items-center p-12.5">
      <div className="container">
        <div className="flex justify-center px-2.5">
          <div className="2xl:w-4/10 md:w-1/2 sm:w-2/3 w-full">
            <div className="absolute end-0 top-0">
              <img src={authCard} alt="auth-card-bg" />
            </div>

            <div className="absolute start-0 bottom-0 rotate-180">
              <img src={authCard} alt="auth-card-bg" />
            </div>
            <div className="card p-7.5 rounded-2xl">
              <div className="mb-5 flex flex-col items-center justify-center text-center">
                <AuthLogo />
              </div>

              <form>
                <div className="mt-3 mb-9">
                  <div className="bg-default-50 border-light mx-auto flex size-20 items-center justify-center rounded-full border border-dashed">
                    <img src={deleteImg} alt="checkmark" className="size-16 object-contain" />
                  </div>
                </div>

                <h4 className="mb-6 text-center text-lg font-bold">Account Deactivated</h4>
                <p className="text-default-400 mb-9 text-center">Your account is currently inactive. Reactivate now to regain access to all features and opportunities.</p>

                <div>
                  <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover">
                    Reactivate Now
                  </button>
                </div>
              </form>
            </div>

            <p className="text-default-400 mt-7.5 text-center">
              © {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>{' '}
            </p>
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
