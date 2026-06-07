import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/auth-card-bg.svg'
import authImg from '@/images/auth.jpg'
import AuthLogo from '@/components/AuthLogo'
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
                    <h4 className="text-default-900 mt-7.5 mb-9 text-base font-bold">Request sent Successfully!</h4>
                    <h4 className="text-default-400 mx-auto mb-6">We&apos;ve emailed you a 6-digit verification code we sent to</h4>
                    <div className="text-2xl font-bold">******6789</div>
                  </div>

                  <form>
                    <div className="mb-5">
                      <label htmlFor="userEmail" className="form-label">
                        Enter your 6-digit code
                        <span className="text-danger">*</span>
                      </label>

                      <div className="two-factor flex gap-2">
                        <input type="text" className="form-input text-center" required />
                        <input type="text" className="form-input text-center" required />
                        <input type="text" className="form-input text-center" required />
                        <input type="text" className="form-input text-center" required />
                        <input type="text" className="form-input text-center" required />
                        <input type="text" className="form-input text-center" required />
                      </div>
                    </div>

                    <div>
                      <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover">
                        Confirm
                      </button>
                    </div>
                  </form>

                  <p className="text-default-400 mt-7.5 text-center">
                    Don’t have a code?&nbsp;
                    <Link href="#" className="text-primary font-semibold underline underline-offset-3">
                      Resend&nbsp;
                    </Link>
                    or&nbsp;
                    <Link href="#" className="text-primary font-semibold underline underline-offset-3">
                      Call Us
                    </Link>
                  </p>

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
