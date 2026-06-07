import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import authImg from '@/images/admin/auth.jpg'
import AuthLogo from '@/components/AuthLogo'
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
              <p className="text-default-400 mx-auto mb-4 w-full text-center lg:w-72">We&apos;ve emailed you a 6-digit verification code we sent to</p>
              <div className="text-center text-2xl font-bold">******6789</div>
              <form className="mt-6">
                <div className="mb-6">
                  <label htmlFor="userEmail" className="form-label">
                    Enter your 6-digit code
                    <span className="text-danger">*</span>
                  </label>

                  <div className="two-factor flex w-81 gap-2">
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
