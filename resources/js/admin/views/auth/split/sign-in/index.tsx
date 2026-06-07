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
              <h4 className="font-bold mb-2 text-default-900 text-lg text-center">Great to see you here 👋</h4>
              <p className="text-default-400 mb-4 mx-auto w-full text-center lg:w-3/4">You’re just one step away - sign in to continue.</p>

              <div className="grid lg:grid-cols-2 text-default-400 gap-3">
                <div>
                  <Link href="#!" className="btn border border-default-300 text-default-900 hover:border-default-400 hover:bg-default-50 w-full">
                    Sign in with
                    <svg xmlns="http://www.w3.org/2000/svg" className="ms-1" width="13.68px" height="14px" viewBox="0 0 256 262">
                      <path fill="#4285f4" d="M255.878 133.451c0-10.734-.871-18.567-2.756-26.69H130.55v48.448h71.947c-1.45 12.04-9.283 30.172-26.69 42.356l-.244 1.622l38.755 30.023l2.685.268c24.659-22.774 38.875-56.282 38.875-96.027"></path>
                      <path fill="#34a853" d="M130.55 261.1c35.248 0 64.839-11.605 86.453-31.622l-41.196-31.913c-11.024 7.688-25.82 13.055-45.257 13.055c-34.523 0-63.824-22.773-74.269-54.25l-1.531.13l-40.298 31.187l-.527 1.465C35.393 231.798 79.49 261.1 130.55 261.1"></path>
                      <path fill="#fbbc05" d="M56.281 156.37c-2.756-8.123-4.351-16.827-4.351-25.82c0-8.994 1.595-17.697 4.206-25.82l-.073-1.73L15.26 71.312l-1.335.635C5.077 89.644 0 109.517 0 130.55s5.077 40.905 13.925 58.602z"></path>
                      <path fill="#eb4335" d="M130.55 50.479c24.514 0 41.05 10.589 50.479 19.438l36.844-35.974C195.245 12.91 165.798 0 130.55 0C79.49 0 35.393 29.301 13.925 71.947l42.211 32.783c10.59-31.477 39.891-54.251 74.414-54.251"></path>
                    </svg>
                  </Link>
                </div>
                <div>
                  <Link href="#!" className="btn border border-default-300 text-default-900 hover:border-default-400 hover:bg-default-50 w-full">
                    Sign in with
                    <svg xmlns="http://www.w3.org/2000/svg" className="ms-1" width="14px" height="14px" viewBox="0 0 64 64">
                      <path
                        fill="currentColor"
                        d="M32 0C14 0 0 14 0 32c0 21 19 30 22 30c2 0 2-1 2-2v-5c-7 2-10-2-11-5c0 0 0-1-2-3c-1-1-5-3-1-3c3 0 5 4 5 4c3 4 7 3 9 2c0-2 2-4 2-4c-8-1-14-4-14-15q0-6 3-9s-2-4 0-9c0 0 5 0 9 4c3-2 13-2 16 0c4-4 9-4 9-4c2 7 0 9 0 9q3 3 3 9c0 11-7 14-14 15c1 1 2 3 2 6v8c0 1 0 2 2 2c3 0 22-9 22-30C64 14 50 0 32 0"
                      ></path>
                    </svg>
                  </Link>
                </div>
              </div>

              <p className="relative my-5 text-center text-default-400 after:absolute after:start-0 after:end-0 after:top-2.75 after:h-0.75 after:border-t after:border-b after:border-dashed after:border-default-300">
                <span className="relative z-10 bg-card font-medium px-4">Continue with Email</span>
              </p>

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
                  <label htmlFor="userPassword" className="form-label">
                    Password
                    <span className="text-danger">*</span>
                  </label>
                  <div className="input-icon-group">
                    <Icon icon="lock-password" className="input-icon" />
                    <input type="password" className="form-input" id="userPassword" placeholder="••••••••" required />
                  </div>
                </div>

                <div className="mb-5 flex items-center justify-between">
                  <div className="flex items-start gap-2 lg:items-center">
                    <input className="form-checkbox form-checkbox-light mt-1 size-4.25 lg:mt-0" type="checkbox" id="rememberMe" defaultChecked />
                    <label className="form-check-label" htmlFor="rememberMe">
                      Keep me signed in
                    </label>
                  </div>
                  <Link href="/auth/split/reset-pass" className="text-default-400 underline underline-offset-4">
                    Forgot Password?
                  </Link>
                </div>

                <div>
                  <button type="submit" className="btn bg-primary w-full py-3 font-semibold text-white hover:bg-primary-hover">
                    Sign In
                  </button>
                </div>
              </form>

              <p className="text-default-400 mt-7.5 text-center">
                New here?&nbsp;
                <Link href="/auth/split/sign-up" className="text-primary font-semibold underline underline-offset-4">
                  Create an account
                </Link>
              </p>
            </div>

            <p className="text-default-400 mt-7.5 text-center">
              &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
            </p>
          </div>
        </div>

        <div className="hidden w-full md:block">
          <div className="relative h-full overflow-hidden bg-cover bg-center bg-no-repeat" style={{ backgroundImage: `url("${authImg}")` }}>
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
