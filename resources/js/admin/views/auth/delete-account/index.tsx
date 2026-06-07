import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/auth-card-bg.svg'
import authImg from '@/images/auth.jpg'
import deleteImg from '@/images/delete.png'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'


const Page = () => {
  return (
    <div className="flex min-h-screen items-center p-12.5">
      <div className="container">
        <div className="flex justify-center">
          <div className="xl:w-5/6">
            <div className="absolute end-0 top-0">
              <img src={authCard} alt="auth-card-bg" />
            </div>

            <div className="absolute start-0 bottom-0 rotate-180">
              <img src={authCard} alt="auth-card-bg" />
            </div>
            <div className="card rounded-2xl">
              <div className="grid grid-cols-1 lg:grid-cols-2">
                <div className="card-body relative p-12.5">
                  <div className="mb-7.5 flex flex-col items-center justify-center text-center">
                    <AuthLogo />
                  </div>

                  <form>
                    <div className="mt-3 mb-9">
                      <div className="bg-default-50 border-light mx-auto flex size-20 items-center justify-center rounded-full border border-dashed">
                        <img src={deleteImg} alt="delete" className="size-16 object-contain" />
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

                  <p className="text-default-400 mt-7.5 text-center">
                    &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
                  </p>
                </div>
                <div className="relative hidden h-full overflow-hidden rounded-e-2xl bg-cover bg-center object-cover lg:block" style={{ backgroundImage: `url(${authImg})` }}>
                  <div className="absolute inset-0 flex items-end justify-center rounded-e-sm p-9 [background:linear-gradient(to_top,#313a46,rgba(49,58,70,.8),rgba(49,58,70,.5))]"></div>
                </div>
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
