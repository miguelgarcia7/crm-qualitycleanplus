import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'


const Page = () => {
  return (
    <>
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
                <div className="mb-3 flex flex-col items-center justify-center text-center">
                  <AuthLogo />
                  <p className="text-default-400 mx-auto mt-6 mb-4 w-full lg:w-3/4">Enter your email address and we&apos;ll send you a link to reset your password.</p>
                </div>

                <form>
                  <div className="mb-5">
                    <label htmlFor="userEmail" className="form-label">
                      Email address
                      <span className="text-danger">*</span>
                    </label>
                    <div className="input-group">
                      <input type="email" className="form-input" id="userEmail" placeholder="you@example.com" required />
                    </div>
                  </div>

                  <div className="mb-6">
                    <div className="flex items-center gap-2">
                      <input className="form-checkbox form-checkbox-light size-4.5" type="checkbox" id="rememberMe" />
                      <label className="form-check-label" htmlFor="rememberMe">
                        Agree the Terms & Policy
                      </label>
                    </div>
                  </div>

                  <div>
                    <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover">
                      Send Request
                    </button>
                  </div>
                </form>

                <p className="text-default-400 mt-7.5 text-center">
                  Return to&nbsp;
                  <Link href="/auth/sign-in" className="text-primary font-semibold underline underline-offset-4">
                    Sign in
                  </Link>
                </p>
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
