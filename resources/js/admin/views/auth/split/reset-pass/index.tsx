import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import authImg from '@/images/admin/auth.jpg'
import AuthLogo from '@/components/AuthLogo'
import Icon from '@/components/wrappers/Icon'
import { currentYear, META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'


const Page = () => {
  return (
    <div className="min-h-screen">
      <div className="flex h-full w-full">
        <div className="min-w-full md:min-w-106 md:max-w-118">
          <div className="card relative flex min-h-screen flex-col justify-between rounded-none p-12.5">
            <div className="absolute end-0 top-0">
              <img src={authCard} alt="auth-card-bg" className="w-45" />
            </div>

            <div className="mb-7.5 flex flex-col items-center justify-center text-center">
              <AuthLogo />
            </div>

            <div>
              <p className="text-default-400 mx-auto w-full text-center lg:w-72">Enter your email address and we&apos;ll send you a link to reset your password.</p>
              <form className="mt-9">
                <div className="mb-6">
                  <label htmlFor="userEmail" className="form-label">
                    Email address
                    <span className="text-danger">*</span>
                  </label>
                  <div className="input-icon-group">
                    <Icon icon="mail" className="input-icon" />
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
                <Link href="/auth/split/sign-in" className="text-primary font-semibold underline underline-offset-4">
                  Sign in
                </Link>
              </p>
            </div>

            <p className="text-default-400 mt-7.5 text-center">
              &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
            </p>
          </div>
        </div>

        <div className="hidden w-full md:block">
          <div className="relative h-full overflow-hidden  bg-cover bg-center bg-no-repeat" style={{ backgroundImage: `url("${authImg}")` }}>
            <div className="from-zinc-800 via-zinc-800/80 to-zinc-800/50 absolute inset-0 flex items-end justify-center bg-linear-to-t p-9"></div>
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
