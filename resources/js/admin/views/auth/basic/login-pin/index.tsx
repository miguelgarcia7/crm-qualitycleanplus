import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import user1 from '@/images/admin/users/user-1.jpg'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'


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
              <div className="mb-3 flex flex-col items-center justify-center text-center">
                <AuthLogo />
                <p className="text-default-400 mx-auto mt-6 mb-4 w-full lg:w-3/4">This screen is locked. Enter your PIN to continue.</p>
              </div>

              <form>
                <div className="mb-9 text-center">
                  <div className="border-default-300 mx-auto mb-3 size-20 rounded-full border-4">
                    <img src={user1} className="size-18 rounded-full" alt="thumbnail" />
                  </div>
                  <h5 className="text-lg">{META_DATA.username}</h5>
                </div>

                <div className="mb-5">
                  <label htmlFor="userEmail" className="form-label">
                    Enter your 6-digit code
                    <span className="text-danger">*</span>
                  </label>

                  <div className="two-factor flex gap-2">
                    <input type="text" className="form-input text-center" required inputMode="numeric" maxLength={1} />
                    <input type="text" className="form-input text-center" required inputMode="numeric" maxLength={1} />
                    <input type="text" className="form-input text-center" required inputMode="numeric" maxLength={1} />
                    <input type="text" className="form-input text-center" required inputMode="numeric" maxLength={1} />
                    <input type="text" className="form-input text-center" required inputMode="numeric" maxLength={1} />
                    <input type="text" className="form-input text-center" required inputMode="numeric" maxLength={1} />
                  </div>
                </div>

                <div>
                  <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover">
                    Confirm
                  </button>
                </div>
              </form>

              <p className="text-default-400 mt-7.5 text-center">
                Not you? Return to&nbsp;
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
  )
}


Page.layout = (page: React.ReactNode) => (
  <BaseLayout>{page}</BaseLayout>
)

export default Page
