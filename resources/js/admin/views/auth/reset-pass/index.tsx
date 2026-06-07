import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/auth-card-bg.svg'
import authImg from '@/images/auth.jpg'
import AuthLogo from '@/components/AuthLogo'
import Icon from '@/components/wrappers/Icon'
import { currentYear, META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'


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
                    <h4 className="text-default-900 mt-7.5 mb-2 text-base font-bold">Forgot Password ?</h4>
                    <p className="text-default-400 mx-auto w-full lg:w-3/4">Enter your email address and we&apos;ll send you a link to reset your password.</p>
                  </div>

                  <form>
                    <div className="mb-5">
                      <label htmlFor="userEmail" className="form-label">
                        Email address
                        <span className="text-danger">*</span>
                      </label>
                      <div className="input-icon-group">
                        <Icon icon="mail" className="input-icon" />
                        <input type="email" className="form-input" id="userEmail" placeholder="you@example.com" required />
                      </div>
                    </div>

                    <div className="mb-5">
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
                    <Link href="/auth/card/sign-in" className="text-primary font-semibold underline underline-offset-4">
                      Sign in
                    </Link>
                  </p>

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
