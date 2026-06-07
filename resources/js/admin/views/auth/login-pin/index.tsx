import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/auth-card-bg.svg'
import authImg from '@/images/auth.jpg'
import user1 from '@/images/users/user-1.jpg'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'


const Page = () => {
  return (
    <>
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
                      <h4 className="text-default-900 mt-7.5 mb-2 text-base font-bold">Welcome to Admin</h4>
                      <p className="text-default-400 mx-auto w-full">This screen is locked. Enter your PIN to continue.</p>
                    </div>

                    <form>
                      <div className="mb-9 text-center">
                        <div className="border-default-300 mx-auto mb-3 size-20 rounded-full border-4">
                          <img src={user1} className="size-18 rounded-full" alt="thumbnail" />
                        </div>
                        <h5 className="text-base">{META_DATA.username}</h5>
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
                      <Link href="/auth/card/sign-in" className="text-primary font-semibol underline underline-offset-3">
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
    </>
  )
}


Page.layout = (page: React.ReactNode) => (
  <BaseLayout>{page}</BaseLayout>
)

export default Page
